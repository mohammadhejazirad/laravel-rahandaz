[Unit]
Description=Rahandaz scheduler tick

[Service]
Type=oneshot
User=rahandaz-svc
Group=rahandaz-svc
WorkingDirectory=@@RELEASE@@
ExecStart=@@PHP_BIN@@ artisan schedule:run
UMask=0077
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=strict
ProtectHome=true
ReadWritePaths=@@DATA_ROOT@@ /var/log/rahandaz
