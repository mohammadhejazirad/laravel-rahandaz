<?php

declare(strict_types=1);

namespace App\Domain\Docker;

final class DockerAppCatalog
{
    /**
     * @return list<array{id: string, name: string, summary: string, ports: string, env: list<array{name: string, label: string}>, compose: string}>
     */
    public function all(): array
    {
        return [
            $this->app('rabbitmq', 'RabbitMQ', 'صف پیام با کنسول مدیریت.', '15672', [
                ['name' => 'RABBITMQ_DEFAULT_USER', 'label' => 'نام کاربر'],
                ['name' => 'RABBITMQ_DEFAULT_PASS', 'label' => 'گذرواژه'],
            ], <<<'YAML'
services:
  rabbitmq:
    image: rabbitmq:3.13-management
    environment:
      RABBITMQ_DEFAULT_USER: ${RABBITMQ_DEFAULT_USER}
      RABBITMQ_DEFAULT_PASS: ${RABBITMQ_DEFAULT_PASS}
    ports:
      - "5672:5672"
      - "15672:15672"
YAML),
            $this->app('metabase', 'Metabase', 'داشبورد روی دیتابیس پروژه‌ها.', '3000', [
                ['name' => 'MB_DB_TYPE', 'label' => 'نوع پایگاه'],
                ['name' => 'MB_DB_HOST', 'label' => 'میزبان پایگاه'],
                ['name' => 'MB_DB_PORT', 'label' => 'پورت پایگاه'],
                ['name' => 'MB_DB_DBNAME', 'label' => 'نام پایگاه'],
                ['name' => 'MB_DB_USER', 'label' => 'کاربر پایگاه'],
                ['name' => 'MB_DB_PASS', 'label' => 'گذرواژه پایگاه'],
            ], <<<'YAML'
services:
  metabase:
    image: metabase/metabase:v0.51.3
    environment:
      MB_DB_TYPE: ${MB_DB_TYPE}
      MB_DB_HOST: ${MB_DB_HOST}
      MB_DB_PORT: ${MB_DB_PORT}
      MB_DB_DBNAME: ${MB_DB_DBNAME}
      MB_DB_USER: ${MB_DB_USER}
      MB_DB_PASS: ${MB_DB_PASS}
    ports:
      - "3000:3000"
YAML),
            $this->app('n8n', 'n8n', 'اتوماسیون گردش کار.', '5678', [
                ['name' => 'N8N_ENCRYPTION_KEY', 'label' => 'کلید رمزنگاری'],
            ], <<<'YAML'
services:
  n8n:
    image: n8nio/n8n:1.68.1
    environment:
      N8N_ENCRYPTION_KEY: ${N8N_ENCRYPTION_KEY}
    ports:
      - "5678:5678"
YAML),
            $this->app('rocketchat', 'Rocket.Chat', 'گفت‌وگوی تیمی همراه MongoDB خودش.', '3100', [
                ['name' => 'MONGO_INITDB_ROOT_PASSWORD', 'label' => 'گذرواژه MongoDB'],
                ['name' => 'ROCKETCHAT_ADMIN_PASSWORD', 'label' => 'گذرواژه مدیر'],
            ], <<<'YAML'
services:
  mongo:
    image: mongo:7
    environment:
      MONGO_INITDB_ROOT_USERNAME: rocketchat
      MONGO_INITDB_ROOT_PASSWORD: ${MONGO_INITDB_ROOT_PASSWORD}
  rocketchat:
    image: rocket.chat:7.2.0
    environment:
      MONGO_URL: mongodb://rocketchat:${MONGO_INITDB_ROOT_PASSWORD}@mongo:27017/rocketchat?authSource=admin
      ROOT_URL: http://127.0.0.1:3100
      ADMIN_PASS: ${ROCKETCHAT_ADMIN_PASSWORD}
    ports:
      - "3100:3000"
    depends_on:
      - mongo
YAML),
            $this->app('ollama', 'Ollama', 'اجرای مدل زبانی روی همین میزبان.', '11434', [], <<<'YAML'
services:
  ollama:
    image: ollama/ollama:0.4.2
    ports:
      - "11434:11434"
YAML),
            $this->app('open-webui', 'Open WebUI', 'رابط گفت‌وگو برای Ollama در همان Compose.', '3080', [], <<<'YAML'
services:
  ollama:
    image: ollama/ollama:0.4.2
  open-webui:
    image: ghcr.io/open-webui/open-webui:v0.4.8
    environment:
      OLLAMA_BASE_URL: http://ollama:11434
    ports:
      - "3080:8080"
    depends_on:
      - ollama
YAML),
            $this->app('gitlab-ce', 'GitLab CE', 'مخزن گیت خودمیزبان. سنگین است و پورت ۸۰ پنل را اشغال نمی‌کند.', '8929', [
                ['name' => 'GITLAB_ROOT_PASSWORD', 'label' => 'گذرواژه root'],
            ], <<<'YAML'
services:
  gitlab:
    image: gitlab/gitlab-ce:17.5.1-ce.0
    hostname: gitlab.local
    environment:
      GITLAB_OMNIBUS_CONFIG: |
        external_url 'http://127.0.0.1:8929'
        gitlab_rails['initial_root_password'] = '${GITLAB_ROOT_PASSWORD}'
    ports:
      - "8929:8929"
      - "2222:22"
YAML),
            $this->app('chroma', 'Chroma', 'پایگاه برداری برای جست‌وجوی embedding.', '8001', [], <<<'YAML'
services:
  chroma:
    image: chromadb/chroma:0.5.20
    ports:
      - "8001:8000"
YAML),
            $this->app('strapi', 'Strapi', 'مدیریت محتوا. پایگاه را جداگانه آماده کنید.', '1337', [
                ['name' => 'APP_KEYS', 'label' => 'کلیدهای برنامه'],
                ['name' => 'JWT_SECRET', 'label' => 'راز JWT'],
                ['name' => 'DATABASE_HOST', 'label' => 'میزبان پایگاه'],
                ['name' => 'DATABASE_PORT', 'label' => 'پورت پایگاه'],
                ['name' => 'DATABASE_NAME', 'label' => 'نام پایگاه'],
                ['name' => 'DATABASE_USERNAME', 'label' => 'کاربر پایگاه'],
                ['name' => 'DATABASE_PASSWORD', 'label' => 'گذرواژه پایگاه'],
            ], <<<'YAML'
services:
  strapi:
    image: strapi/strapi:4.25.11
    environment:
      APP_KEYS: ${APP_KEYS}
      JWT_SECRET: ${JWT_SECRET}
      DATABASE_CLIENT: postgres
      DATABASE_HOST: ${DATABASE_HOST}
      DATABASE_PORT: ${DATABASE_PORT}
      DATABASE_NAME: ${DATABASE_NAME}
      DATABASE_USERNAME: ${DATABASE_USERNAME}
      DATABASE_PASSWORD: ${DATABASE_PASSWORD}
    ports:
      - "1337:1337"
YAML),
            $this->app('qdrant', 'Qdrant', 'پایگاه برداری Qdrant.', '6333', [
                ['name' => 'QDRANT_API_KEY', 'label' => 'کلید API'],
            ], <<<'YAML'
services:
  qdrant:
    image: qdrant/qdrant:v1.12.1
    environment:
      QDRANT__SERVICE__API_KEY: ${QDRANT_API_KEY}
    ports:
      - "6333:6333"
YAML),
        ];
    }

    /**
     * @return array{id: string, name: string, summary: string, ports: string, env: list<array{name: string, label: string}>, compose: string}|null
     */
    public function find(string $id): ?array
    {
        foreach ($this->all() as $app) {
            if ($app['id'] === $id) {
                return $app;
            }
        }

        return null;
    }

    /**
     * @param  list<array{name: string, label: string}>  $env
     * @return array{id: string, name: string, summary: string, ports: string, env: list<array{name: string, label: string}>, compose: string}
     */
    private function app(string $id, string $name, string $summary, string $ports, array $env, string $compose): array
    {
        return ['id' => $id, 'name' => $name, 'summary' => $summary, 'ports' => $ports, 'env' => $env, 'compose' => $compose];
    }
}
