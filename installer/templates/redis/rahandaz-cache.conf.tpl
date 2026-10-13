bind 127.0.0.1
port @@CACHE_PORT@@
protected-mode yes
supervised systemd
daemonize no
dir @@DATA_DIR@@
appendonly no
save ""
