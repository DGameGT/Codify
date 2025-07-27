<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DGXO | Codify</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-okaidia.min.css" rel="stylesheet" />

    <style>
        :root {
            --font-sans: 'Inter', sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
            --radius: 0.8rem;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            
            --bg-primary: #000000;
            --bg-secondary: #111111;
            --bg-tertiary: #1a1a1a;
            --border-color: rgba(255, 255, 255, 0.1);
            --text-primary: #f5f5f5;
            --text-secondary: #a3a3a3;
            --accent-primary: #06b6d4;
            --accent-glow: rgba(6, 182, 212, 0.2);
            --method-post: #22c55e;
            --method-put: #f59e0b;
            --method-delete: #ef4444;
        }

        @keyframes background-pan {
            from { background-position: 0% center; }
            to { background-position: -200% center; }
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: var(--font-sans);
            background-color: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            background-image: linear-gradient(to right, var(--bg-primary), #081c24, var(--bg-primary));
            background-size: 200% auto;
            animation: background-pan 25s linear infinite;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        .page-header {
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(12px);
            z-index: 100;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        .logo {
            font-size: 1.75rem;
            font-weight: 900;
            color: var(--text-primary);
            text-decoration: none;
            letter-spacing: -1.5px;
        }
        .logo span { color: var(--accent-primary); }
        
        .search-wrapper {
            flex-grow: 1;
            max-width: 500px;
            position: relative;
        }

        #api-search {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            background-color: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            color: var(--text-primary);
            font-size: 1rem;
            transition: var(--transition);
        }
        #api-search:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 2px var(--accent-glow);
        }
        .search-wrapper .search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
            position: relative;
        }

        .notification-bell {
            cursor: pointer;
            position: relative;
            color: var(--text-secondary);
            transition: color 0.3s;
        }
        .notification-bell:hover { color: var(--text-primary); }
        .notification-bell .dot {
            width: 8px;
            height: 8px;
            background-color: var(--accent-primary);
            border-radius: 50%;
            position: absolute;
            top: 0;
            right: 0;
            border: 2px solid var(--bg-primary);
        }

        .notification-box {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 320px;
            background-color: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            padding: 1rem;
            z-index: 110;
            opacity: 0;
            transform: translateY(10px);
            visibility: hidden;
            transition: opacity 0.3s, transform 0.3s, visibility 0.3s;
        }
        .notification-box.active {
            opacity: 1;
            transform: translateY(0);
            visibility: visible;
        }
        .notification-box h4 {
            margin-bottom: 1rem;
            color: var(--text-primary);
        }
        .notification-box ul { list-style: none; }
        .notification-box li {
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.9rem;
        }
        .notification-box li:last-child { border: none; }
        .notification-box .update-title { display: block; font-weight: 600; color: var(--text-primary); }
        .notification-box .update-time { font-size: 0.8rem; color: var(--text-secondary); }

        .main-content { padding: 4rem 0; }
        .api-card {
            background-color: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            overflow: hidden;
            transition: var(--transition);
        }
        .api-card-header {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            background-color: var(--bg-tertiary);
            border-bottom: 1px solid var(--border-color);
        }
        .method-badge {
            padding: 0.35rem 0.85rem;
            border-radius: 99px;
            font-size: 0.8rem;
            font-weight: 700;
            font-family: var(--font-mono);
            color: #fff;
        }
        .method-badge.post { background-color: var(--method-post); }
        .method-badge.put { background-color: var(--method-put); }
        .method-badge.delete { background-color: var(--method-delete); }

        .api-title { font-size: 1.25rem; font-weight: 600; }
        .api-description { color: var(--text-secondary); margin-top: 0.25rem; }
        .api-card-body { display: flex; }
        
        .tabs-nav {
            padding: 1rem;
            border-right: 1px solid var(--border-color);
            flex-shrink: 0;
        }
        .tab-btn {
            display: block;
            width: 100%;
            text-align: left;
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            background: none;
            border: 1px solid transparent;
            color: var(--text-secondary);
            border-radius: 0.5rem;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: var(--transition);
        }
        .tab-btn:hover { background-color: var(--bg-tertiary); color: var(--text-primary); }
        .tab-btn.active {
            color: var(--accent-primary);
            background-color: var(--accent-glow);
            border-color: var(--accent-primary);
        }

        .tab-content {
            flex-grow: 1;
            min-width: 0;
        }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }
        .tab-pane pre[class*="language-"] {
            margin: 0;
            border-radius: 0;
            background: transparent !important;
            height: 100%;
        }

        .footer {
            border-top: 1px solid var(--border-color);
            padding: 3rem 0;
            text-align: center;
            color: var(--text-secondary);
            margin-top: 4rem;
        }
        .footer a { color: var(--accent-primary); text-decoration: none; }

        @media (max-width: 900px) {
            .api-card-body { flex-direction: column; }
            .tabs-nav {
                display: flex;
                overflow-x: auto;
                border-right: none;
                border-bottom: 1px solid var(--border-color);
                padding: 0.5rem;
            }
            .tab-btn { flex-shrink: 0; }
        }
        @media (max-width: 640px) {
            .header-content { flex-wrap: wrap; }
            .search-wrapper { order: 3; width: 100%; max-width: none; margin-top: 1rem; }
        }
    </style>
</head>
<body>

    <header class="page-header">
        <div class="container">
            <div class="header-content">
                <a href="/" class="logo">Codify<span>.</span>API</a>
                <div class="search-wrapper">
                     <span class="search-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    </span>
                    <input type="text" id="api-search" placeholder="Search for endpoints...">
                </div>
                <div class="header-actions">
                    <div class="notification-bell" id="notification-toggle">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                        <span class="dot"></span>
                    </div>
                    <div class="notification-box" id="notification-container">
                        <h4>Updates & Notifications</h4>
                        <ul>
                            <li><span class="update-title">Modern UI is Live!</span><span class="update-time">2 hours ago</span></li>
                            <li><span class="update-title">Go language examples added.</span><span class="update-time">1 day ago</span></li>
                            <li><span class="update-title">API rate limits updated.</span><span class="update-time">3 days ago</span></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <div class="api-card" data-searchable>
                <div class="api-card-header">
                    <span class="method-badge post">POST</span>
                    <div>
                        <h3 class="api-title">/users.php</h3>
                        <p class="api-description">Create a new code snippet.</p>
                    </div>
                </div>
                <div class="api-card-body">
                    <nav class="tabs-nav">
                        <button class="tab-btn active" data-target="js-post">JavaScript</button>
                        <button class="tab-btn" data-target="python-post">Python</button>
                        <button class="tab-btn" data-target="node-post">Node.js</button>
                        <button class="tab-btn" data-target="go-post">Go</button>
                    </nav>
                    <div class="tab-content">
                        <div class="tab-pane active" id="js-post">
<pre><code class="language-js">const apiUrl = 'https://codeshare.cloudku.click/users.php';
const apiKey = 'YOUR_API_KEY';

const snippetData = {
  title: "My Awesome JS Snippet",
  code_content: "const greet = () => 'Hello from the cloud!';",
  language: "javascript"
};

async function createSnippet() {
  const response = await fetch(apiUrl, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${apiKey}`
    },
    body: JSON.stringify(snippetData)
  });
  const result = await response.json();
  console.log(result);
}

createSnippet();
</code></pre>
                        </div>
                        <div class="tab-pane" id="python-post">
<pre><code class="language-python">import requests
import json

api_url = "https://codeshare.cloudku.click/users.php"
api_key = "YOUR_API_KEY"

headers = {
    "Content-Type": "application/json",
    "Authorization": f"Bearer {api_key}"
}

snippet_data = {
    "title": "My Cool Python Snippet",
    "code_content": "def main():\n    print('Hello from Python!')",
    "language": "python"
}

response = requests.post(api_url, headers=headers, data=json.dumps(snippet_data))
print(response.json())
</code></pre>
                        </div>
                        <div class="tab-pane" id="node-post">
<pre><code class="language-js">const axios = require('axios');

const apiUrl = 'https://codeshare.cloudku.click/users.php';
const apiKey = 'YOUR_API_KEY';

const snippetData = {
  title: "A Powerful Node.js Snippet",
  code_content: "const http = require('http');",
  language: "javascript"
};

const createSnippet = async () => {
  try {
    const response = await axios.post(apiUrl, snippetData, {
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${apiKey}`
      }
    });
    console.log('Success:', response.data);
  } catch (error) {
    console.error('Error:', error.response ? error.response.data : error.message);
  }
};

createSnippet();
</code></pre>
                        </div>
                        <div class="tab-pane" id="go-post">
<pre><code class="language-go">package main

import (
	"bytes"
	"encoding/json"
	"fmt"
	"net/http"
)

func main() {
	apiURL := "https://codeshare.cloudku.click/users.php"
	apiKey := "YOUR_API_KEY"

	snippetData := map[string]string{
		"title": "Efficient Go Snippet",
		"code_content": "package main\n\nimport \"fmt\"\n\nfunc main() { fmt.Println(\"Hello, Go!\") }",
		"language": "go",
	}

	jsonData, _ := json.Marshal(snippetData)
	req, _ := http.NewRequest("POST", apiURL, bytes.NewBuffer(jsonData))
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Authorization", "Bearer "+apiKey)

	client := &http.Client{}
	resp, _ := client.Do(req)
	defer resp.Body.Close()

	fmt.Println("Response Status:", resp.Status)
}
</code></pre>
                        </div>
                    </div>
                </div>
            </div>

            <div class="api-card" data-searchable style="margin-top: 2rem;">
                <div class="api-card-header">
                    <span class="method-badge put">PUT</span>
                    <div>
                        <h3 class="api-title">/users.php?share_id={id}</h3>
                        <p class="api-description">Update an existing code snippet.</p>
                    </div>
                </div>
                <div class="api-card-body">
                    <nav class="tabs-nav">
                        <button class="tab-btn active" data-target="js-put">JavaScript</button>
                        <button class="tab-btn" data-target="python-put">Python</button>
                        <button class="tab-btn" data-target="node-put">Node.js</button>
                        <button class="tab-btn" data-target="go-put">Go</button>
                    </nav>
                    <div class="tab-content">
                        <div class="tab-pane active" id="js-put">
<pre><code class="language-js">const shareId = 'abc123XY';
const apiUrl = `https://codeshare.cloudku.click/users.php?share_id=${shareId}`;
const apiKey = 'YOUR_API_KEY';

const updatedData = {
  title: "My Updated Title",
  code_content: "console.log('Hello, updated world!');"
};

async function updateSnippet() {
  const response = await fetch(apiUrl, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${apiKey}`
    },
    body: JSON.stringify(updatedData)
  });
  const result = await response.json();
  console.log(result);
}

updateSnippet();
</code></pre>
                        </div>
                        <div class="tab-pane" id="python-put">
<pre><code class="language-python">import requests
import json

share_id = "abc123XY"
api_url = f"https://codeshare.cloudku.click/users.php?share_id={share_id}"
api_key = "YOUR_API_KEY"

headers = {
    "Content-Type": "application/json",
    "Authorization": f"Bearer {api_key}"
}

updated_data = {
    "title": "My Updated Python Title",
    "code_content": "print('This code has been updated.')"
}

response = requests.put(api_url, headers=headers, data=json.dumps(updated_data))
print(response.json())
</code></pre>
                        </div>
                        <div class="tab-pane" id="node-put">
<pre><code class="language-js">const axios = require('axios');

const shareId = 'abc123XY';
const apiUrl = `https://codeshare.cloudku.click/users.php?share_id=${shareId}`;
const apiKey = 'YOUR_API_KEY';

const updatedData = {
  title: "Updated via Node.js"
};

const updateSnippet = async () => {
  try {
    const response = await axios.put(apiUrl, updatedData, {
      headers: { 'Authorization': `Bearer ${apiKey}` }
    });
    console.log('Success:', response.data);
  } catch (error) {
    console.error('Error:', error.response.data);
  }
};

updateSnippet();
</code></pre>
                        </div>
                        <div class="tab-pane" id="go-put">
<pre><code class="language-go">package main

import (
	"bytes"
	"encoding/json"
	"fmt"
	"net/http"
)

func main() {
	shareID := "abc123XY"
	apiURL := fmt.Sprintf("https://codeshare.cloudku.click/users.php?share_id=%s", shareID)
	apiKey := "YOUR_API_KEY"

	updatedData := map[string]string{
		"title": "Updated by Go!",
	}

	jsonData, _ := json.Marshal(updatedData)
	req, _ := http.NewRequest("PUT", apiURL, bytes.NewBuffer(jsonData))
	req.Header.Set("Authorization", "Bearer "+apiKey)
	req.Header.Set("Content-Type", "application/json")
	
    client := &http.Client{}
	resp, _ := client.Do(req)
	defer resp.Body.Close()

	fmt.Println("Response Status:", resp.Status)
}
</code></pre>
                        </div>
                    </div>
                </div>
            </div>

            <div class="api-card" data-searchable style="margin-top: 2rem;">
                <div class="api-card-header">
                    <span class="method-badge delete">DELETE</span>
                    <div>
                        <h3 class="api-title">/users.php?share_id={id}</h3>
                        <p class="api-description">Delete a specific code snippet.</p>
                    </div>
                </div>
                <div class="api-card-body">
                    <nav class="tabs-nav">
                        <button class="tab-btn active" data-target="js-del">JavaScript</button>
                        <button class="tab-btn" data-target="python-del">Python</button>
                        <button class="tab-btn" data-target="node-del">Node.js</button>
                        <button class="tab-btn" data-target="go-del">Go</button>
                    </nav>
                    <div class="tab-content">
                        <div class="tab-pane active" id="js-del">
<pre><code class="language-js">const shareId = 'abc123XY';
const apiUrl = `https://codeshare.cloudku.click/users.php?share_id=${shareId}`;
const apiKey = 'YOUR_API_KEY';

async function deleteSnippet() {
  const response = await fetch(apiUrl, {
    method: 'DELETE',
    headers: {
      'Authorization': `Bearer ${apiKey}`
    }
  });
  const result = await response.json();
  console.log(result);
}

deleteSnippet();
</code></pre>
                        </div>
                        <div class="tab-pane" id="python-del">
<pre><code class="language-python">import requests

share_id = "abc123XY"
api_url = f"https://codeshare.cloudku.click/users.php?share_id={share_id}"
api_key = "YOUR_API_KEY"

headers = { "Authorization": f"Bearer {api_key}" }

response = requests.delete(api_url, headers=headers)
print(response.json())
</code></pre>
                        </div>
                        <div class="tab-pane" id="node-del">
<pre><code class="language-js">const axios = require('axios');

const shareId = 'abc123XY';
const apiUrl = `https://codeshare.cloudku.click/users.php?share_id=${shareId}`;
const apiKey = 'YOUR_API_KEY';

const deleteSnippet = async () => {
  try {
    const response = await axios.delete(apiUrl, {
      headers: { 'Authorization': `Bearer ${apiKey}` }
    });
    console.log('Success:', response.data);
  } catch (error) {
    console.error('Error:', error.response.data);
  }
};

deleteSnippet();
</code></pre>
                        </div>
                        <div class="tab-pane" id="go-del">
<pre><code class="language-go">package main

import (
	"fmt"
	"net/http"
)

func main() {
	shareID := "abc123XY"
	apiURL := fmt.Sprintf("https://codeshare.cloudku.click/users.php?share_id=%s", shareID)
	apiKey := "YOUR_API_KEY"

	req, _ := http.NewRequest("DELETE", apiURL, nil)
	req.Header.Set("Authorization", "Bearer "+apiKey)
	
    client := &http.Client{}
	resp, _ := client.Do(req)
	defer resp.Body.Close()

	fmt.Println("Response Status:", resp.Status)
}
</code></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <footer class="footer">
        <p>&copy; 2025 Codify. Dibuat oleh <a href="https://github.com/dgamegt" target="_blank" rel="noopener noreferrer">DGXO</a>.</p>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.api-card').forEach(card => {
                const tabs = card.querySelectorAll('.tab-btn');
                const panes = card.querySelectorAll('.tab-pane');

                tabs.forEach(tab => {
                    tab.addEventListener('click', () => {
                        const targetId = tab.dataset.target;
                        
                        tabs.forEach(t => t.classList.remove('active'));
                        panes.forEach(p => p.classList.remove('active'));

                        tab.classList.add('active');
                        card.querySelector(`#${targetId}`).classList.add('active');
                    });
                });
            });

            const notifToggle = document.getElementById('notification-toggle');
            const notifBox = document.getElementById('notification-container');
            if (notifToggle && notifBox) {
                notifToggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    notifBox.classList.toggle('active');
                });
                window.addEventListener('click', () => {
                    if (notifBox.classList.contains('active')) {
                        notifBox.classList.remove('active');
                    }
                });
            }

            const searchInput = document.getElementById('api-search');
            const searchableItems = document.querySelectorAll('[data-searchable]');
            if (searchInput) {
                searchInput.addEventListener('input', (e) => {
                    const query = e.target.value.toLowerCase();
                    searchableItems.forEach(item => {
                        const title = item.querySelector('.api-title')?.textContent.toLowerCase() || '';
                        const description = item.querySelector('.api-description')?.textContent.toLowerCase() || '';
                        if (title.includes(query) || description.includes(query)) {
                            item.style.display = 'block';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            }
        });
    </script>

</body>
</html>