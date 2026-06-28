// ws_server.js — WebSocket + interni HTTP push server za instant notifikacije
//
// Pokretanje: node ws_server.js (iz ws-server/ foldera, nakon `npm install`)
// Zaustavljanje: Ctrl+C u terminalu
//
// WebSocket (port 8090) — browser klijenti se konektuju i registruju sa
//   {"type":"auth","korisnik_id":N}
// HTTP (port 8091) — PHP backend (pushNotifikacija() u helpers.php) POST-uje na /push
//   {"korisnik_id":N,"sadrzaj":"...","obavestenje_id":N}
// Server prosledi {"type":"nova_notifikacija",...} svim WS konekcijama tog korisnika.

const http = require('http');
const WebSocket = require('ws');

const WS_PORT = 8090;
const HTTP_PORT = 8091;

// korisnik_id -> Set od WebSocket konekcija (korisnik moze imati vise otvorenih tabova)
const korisnikConn = new Map();

const wss = new WebSocket.Server({ port: WS_PORT });

wss.on('connection', (ws) => {
    ws.on('message', (data) => {
        let msg;
        try {
            msg = JSON.parse(data);
        } catch (e) {
            return;
        }

        if (msg.type === 'auth' && msg.korisnik_id) {
            const korisnikId = parseInt(msg.korisnik_id, 10);
            ws.korisnikId = korisnikId;
            if (!korisnikConn.has(korisnikId)) {
                korisnikConn.set(korisnikId, new Set());
            }
            korisnikConn.get(korisnikId).add(ws);
        }
    });

    ws.on('close', () => {
        if (ws.korisnikId && korisnikConn.has(ws.korisnikId)) {
            korisnikConn.get(ws.korisnikId).delete(ws);
            if (korisnikConn.get(ws.korisnikId).size === 0) {
                korisnikConn.delete(ws.korisnikId);
            }
        }
    });
});

console.log(`WebSocket server slusa na portu ${WS_PORT}`);

const httpServer = http.createServer((req, res) => {
    if (req.method === 'POST' && req.url === '/push') {
        let body = '';
        req.on('data', (chunk) => { body += chunk; });
        req.on('end', () => {
            let data;
            try {
                data = JSON.parse(body);
            } catch (e) {
                res.writeHead(400, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({ ok: false, error: 'invalid json' }));
                return;
            }

            const korisnikId = parseInt(data.korisnik_id, 10);
            const poruka = JSON.stringify({
                type: 'nova_notifikacija',
                sadrzaj: data.sadrzaj || '',
                obavestenje_id: data.obavestenje_id ?? null,
            });

            if (korisnikConn.has(korisnikId)) {
                for (const ws of korisnikConn.get(korisnikId)) {
                    if (ws.readyState === WebSocket.OPEN) {
                        ws.send(poruka);
                    }
                }
            }

            res.writeHead(200, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ ok: true }));
        });
        return;
    }

    res.writeHead(404, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ ok: false, error: 'not found' }));
});

httpServer.listen(HTTP_PORT, () => {
    console.log(`HTTP push endpoint slusa na portu ${HTTP_PORT} (POST /push)`);
});
