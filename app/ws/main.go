// WebSocket log streamer for WG-Nginx admin panel.
// Tails log files and streams new lines to connected WebSocket clients.
// ~200 lines of Go, adds ~5MB to the image.
//
// Why Go instead of PHP:
// PHP-FPM workers are designed for short-lived requests (~50ms).
// A WebSocket connection is long-lived (minutes/hours). Holding a
// PHP-FPM worker for that long starves other requests. Go handles
// thousands of concurrent WebSocket connections in a single goroutine.

package main

import (
	"bufio"
	"encoding/json"
	"flag"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"time"

	"golang.org/x/net/websocket"
)

// LogEntry is sent to clients as JSON.
type LogEntry struct {
	Source string `json:"source"`
	Line   string `json:"line"`
	Time   string `json:"time"`
}

// Hub manages connected WebSocket clients.
type Hub struct {
	mu      sync.RWMutex
	clients map[*websocket.Conn]bool
}

func NewHub() *Hub {
	return &Hub{clients: make(map[*websocket.Conn]bool)}
}

func (h *Hub) Add(ws *websocket.Conn) {
	h.mu.Lock()
	h.clients[ws] = true
	h.mu.Unlock()
}

func (h *Hub) Remove(ws *websocket.Conn) {
	h.mu.Lock()
	delete(h.clients, ws)
	h.mu.Unlock()
}

// Broadcast sends a log entry to all connected clients.
func (h *Hub) Broadcast(entry LogEntry) {
	data, err := json.Marshal(entry)
	if err != nil {
		return
	}
	msg := string(data)

	h.mu.RLock()
	defer h.mu.RUnlock()

	for ws := range h.clients {
		// Non-blocking write with timeout
		ws.SetWriteDeadline(time.Now().Add(5 * time.Second))
		if _, err := ws.Write([]byte(msg)); err != nil {
			// Client disconnected — will be cleaned up by handler
			ws.Close()
		}
	}
}

// tailFile watches a log file and sends new lines to the hub.
func tailFile(path string, hub *Hub) {
	source := strings.TrimSuffix(filepath.Base(path), ".log")

	for {
		file, err := os.Open(path)
		if err != nil {
			time.Sleep(2 * time.Second)
			continue
		}

		// Seek to end — only stream new lines
		file.Seek(0, io.SeekEnd)

		scanner := bufio.NewScanner(file)
		for {
			if scanner.Scan() {
				line := scanner.Text()
				if line == "" {
					continue
				}
				hub.Broadcast(LogEntry{
					Source: source,
					Line:   line,
					Time:   time.Now().Format("15:04:05"),
				})
			} else {
				// No new data — wait briefly then check again
				time.Sleep(200 * time.Millisecond)

				// Check if file was rotated (size smaller than position)
				info, err := file.Stat()
				if err != nil {
					break // File gone — reopen
				}
				pos, _ := file.Seek(0, io.SeekCurrent)
				if info.Size() < pos {
					break // File truncated — reopen from start
				}
			}
		}
		file.Close()
	}
}

func main() {
	port := flag.Int("port", 6790, "WebSocket server port")
	logDir := flag.String("logdir", "/data/logs", "Directory containing log files")
	flag.Parse()

	hub := NewHub()

	// Tail all .log files in the log directory
	files, _ := filepath.Glob(filepath.Join(*logDir, "*.log"))
	for _, f := range files {
		log.Printf("Tailing: %s", f)
		go tailFile(f, hub)
	}

	// Also watch for new log files appearing
	go func() {
		known := make(map[string]bool)
		for _, f := range files {
			known[f] = true
		}
		for {
			time.Sleep(10 * time.Second)
			current, _ := filepath.Glob(filepath.Join(*logDir, "*.log"))
			for _, f := range current {
				if !known[f] {
					log.Printf("New log file detected, tailing: %s", f)
					known[f] = true
					go tailFile(f, hub)
				}
			}
		}
	}()

	// WebSocket handler
	http.Handle("/ws", websocket.Handler(func(ws *websocket.Conn) {
		hub.Add(ws)
		defer hub.Remove(ws)

		log.Printf("Client connected: %s", ws.Request().RemoteAddr)

		// Send a welcome message
		welcome := LogEntry{
			Source: "system",
			Line:   "WebSocket connected. Streaming logs in real time.",
			Time:   time.Now().Format("15:04:05"),
		}
		data, _ := json.Marshal(welcome)
		ws.Write(data)

		// Keep the connection alive by reading (client may send commands)
		buf := make([]byte, 1024)
		for {
			_, err := ws.Read(buf)
			if err != nil {
				break // Client disconnected
			}
		}

		log.Printf("Client disconnected: %s", ws.Request().RemoteAddr)
	}))

	// Health check
	http.HandleFunc("/health", func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		fmt.Fprintf(w, `{"status":"ok","clients":%d}`, len(hub.clients))
	})

	addr := fmt.Sprintf("127.0.0.1:%d", *port)
	log.Printf("WebSocket server listening on %s", addr)
	log.Fatal(http.ListenAndServe(addr, nil))
}
