const WebSocket = require('ws');
const mysql = require('mysql2');

const wss = new WebSocket.Server({ port: 8080 });

// Database connection
const pool = mysql.createPool({
  host: 'localhost',
  user: 'root',
  password: '',
  database: 'echec_db',
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0
});
const promisePool = pool.promise();

// Map WebSocket to client data { gameId: string, userId: string }
const clients = new Map();

wss.on('connection', function connection(ws) {
  ws.on('message', function incoming(message) {
    try {
      const data = JSON.parse(message);
      
      if (data.type === 'subscribe') {
        const info = clients.get(ws) || {};
        info.gameId = data.gameId;
        clients.set(ws, info);
        console.log(`Client subscribed to game ${data.gameId}`);
      } 
      else if (data.type === 'subscribe_global') {
        const info = clients.get(ws) || {};
        info.userId = data.userId;
        clients.set(ws, info);
        console.log(`User ${data.userId} subscribed globally`);
        
        promisePool.execute('UPDATE users SET is_online = 1, last_seen = NOW() WHERE id = ?', [data.userId])
          .then(() => {
            wss.clients.forEach(function each(client) {
              if (client.readyState === WebSocket.OPEN) {
                client.send(JSON.stringify({ type: 'active_users_changed' }));
              }
            });
          })
          .catch(err => console.error('Error updating online status:', err));
      }
      else if (['game_updated', 'new_message', 'emoji_rain', 'spectator_joined'].includes(data.type)) {
        // Broadcast to specific game
        const gameId = data.gameId;
        wss.clients.forEach(function each(client) {
          if (client !== ws && client.readyState === WebSocket.OPEN) {
            const clientInfo = clients.get(client);
            if (clientInfo && clientInfo.gameId == gameId) {
              client.send(JSON.stringify({ type: data.type, gameId }));
            }
          }
        });
      }
      else if (['private_message_sent', 'invitation_sent', 'invitation_responded'].includes(data.type)) {
        // Broadcast to specific user
        const receiverId = data.receiverId;
        wss.clients.forEach(function each(client) {
          if (client !== ws && client.readyState === WebSocket.OPEN) {
            const clientInfo = clients.get(client);
            if (clientInfo && clientInfo.userId == receiverId) {
              client.send(JSON.stringify({ type: data.type, senderId: data.senderId }));
            }
          }
        });
      }
      else if (data.type === 'global_update' || data.type === 'active_users_changed') {
        // Broadcast to everyone
        wss.clients.forEach(function each(client) {
          if (client !== ws && client.readyState === WebSocket.OPEN) {
            client.send(JSON.stringify({ type: data.type }));
          }
        });
      }
    } catch (e) {
      console.error("Error parsing message", e);
    }
  });

  ws.on('close', () => {
    const info = clients.get(ws);
    const userId = info && info.userId ? info.userId : null;
    clients.delete(ws);
    
    if (userId) {
      let isStillActive = false;
      for (const [client, cInfo] of clients.entries()) {
        if (cInfo.userId == userId && client.readyState === WebSocket.OPEN) {
          isStillActive = true;
          break;
        }
      }
      
      if (!isStillActive) {
        promisePool.execute('UPDATE users SET is_online = 0 WHERE id = ?', [userId])
          .then(() => {
            wss.clients.forEach(function each(client) {
              if (client.readyState === WebSocket.OPEN) {
                client.send(JSON.stringify({ type: 'active_users_changed' }));
              }
            });
          })
          .catch(err => console.error('Error setting offline status:', err));
      }
    }
  });
});

console.log('WebSocket relay server running on port 8080');
