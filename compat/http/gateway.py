"""Isolated GET-only CGI gateway for PHP 5.2 (no Apache, no public port)."""
import os
import subprocess
import sys
from http.server import BaseHTTPRequestHandler, HTTPServer
from urllib.parse import urlsplit


class Gateway(BaseHTTPRequestHandler):
    def do_GET(self):
        url = urlsplit(self.path)
        if url.path == '/__compat/health':
            self.send_response(200)
            self.end_headers()
            self.wfile.write(b'ready')
            return
        env = dict(os.environ, REDIRECT_STATUS='200', REQUEST_METHOD='GET',
                   QUERY_STRING=url.query, SCRIPT_FILENAME='/opt/megalopolis-r46/index.php',
                   SCRIPT_NAME='/index.php', PHP_SELF='/index.php', REQUEST_URI=self.path,
                   SERVER_PROTOCOL='HTTP/1.1', GATEWAY_INTERFACE='CGI/1.1',
                   SERVER_NAME='compat.test', SERVER_PORT='8080', REMOTE_ADDR='127.0.0.1')
        for key, value in self.headers.items():
            env['HTTP_' + key.upper().replace('-', '_')] = value
        try:
            result = subprocess.run(['/opt/php52/bin/php-cgi'], env=env,
                                    capture_output=True, timeout=60)
            if result.stderr:
                sys.stderr.buffer.write(result.stderr)
                sys.stderr.buffer.flush()
            headers, body = result.stdout.split(b'\r\n\r\n', 1)
            fields = [line.decode('latin1').split(':', 1) for line in headers.split(b'\r\n')]
            status = next((int(v.strip().split()[0]) for k, v in fields if k.lower() == 'status'), 200)
            if result.returncode:
                status = 500
            self.send_response(status)
            for key, value in fields:
                if key.lower() not in ('status', 'connection', 'content-length'):
                    self.send_header(key, value.strip())
            self.send_header('Content-Length', str(len(body)))
            self.end_headers()
            self.wfile.write(body)
        except (ValueError, subprocess.TimeoutExpired) as error:
            self.send_error(502, str(error))

    def log_message(self, *args):
        pass


HTTPServer(('0.0.0.0', 8080), Gateway).serve_forever()
