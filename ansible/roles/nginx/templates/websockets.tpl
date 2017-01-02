# http://stackoverflow.com/questions/24237184/how-to-configure-socket-io-to-run-on-same-port-on-https/24374974

# prevents 502 bad gateway error
large_client_header_buffers 8 32k;

location /realtime {

    proxy_set_header Accept-Encoding "";
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-NginX-Proxy true;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;

    proxy_buffers 8 32k;
    proxy_buffer_size 64k;

    proxy_set_header X-Forwarded-Proto $scheme;

    proxy_pass http://127.0.0.1:8000; # put the port of your node app here
    proxy_redirect off;
}

location /tracking {

    proxy_set_header Accept-Encoding "";
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-NginX-Proxy true;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;

    proxy_buffers 8 32k;
    proxy_buffer_size 64k;

    proxy_set_header X-Forwarded-Proto $scheme;

    proxy_pass http://127.0.0.1:8001; # put the port of your node app here
    proxy_redirect off;
}

location /order-tracking {

    proxy_set_header Accept-Encoding "";
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-NginX-Proxy true;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;

    proxy_buffers 8 32k;
    proxy_buffer_size 64k;

    proxy_set_header X-Forwarded-Proto $scheme;

    proxy_pass http://127.0.0.1:8002; # put the port of your node app here
    proxy_redirect off;
}