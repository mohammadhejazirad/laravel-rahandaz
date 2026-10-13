[Unit]
Description=Rahandaz Redis cache
After=network.target

[Service]
Type=notify
User=redis
Group=redis
ExecStart=/usr/bin/redis-server @@CONFIG@@
Restart=always
NoNewPrivileges=true

[Install]
WantedBy=multi-user.target
