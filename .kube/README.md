# Kubernetes Configuration for Home Store

## Архитектура

Каждый под запускает одну программу (single-process containers), без супервизора:

```
                    ┌─────────────────────────────────────────────┐
                    │                    Ingress                  │
                    │     store.dev11.ru, api.dev11.ru            │
                    └─────────────────────────────────────────────┘
                                  │
              ┌───────────────────┴───────────────────┐
              │                                       │
   ┌──────────┴──────────┐                 ┌──────────┴──────────┐
   │  www-service ───────┼──── Pod www     │  api-service ───────┼──── Pod api-web
   │  (Nuxt, :3000)      │                 │  (nginx, :80)       │  fastcgi ─┐
   └─────────────────────┘                 └─────────────────────┘           │
                                                                              │
                                            ┌─────────────────────────────────┘
                                            │
                                            │  api-app Service (:9000)
                                            ▼
                                 ┌──────────────────────┐
                                 │  Pod api-app         │
                                 │  (php-fpm, :9000)    │
                                 └──────────────────────┘
                                 ┌──────────────────────┐
                                 │  Pod postgres (:5432) │
                                 └──────────────────────┘
```

## Структура

```
.kube/
├── namespace.yaml        # Namespace home-store
├── configmap.yaml        # Переменные окружения (не секретные)
├── secret.yaml           # APP_KEY, DB_USERNAME, DB_PASSWORD
├── postgres.yaml         # Postgres: ConfigMap init + PVC + Deployment + Service
├── api-php.yaml          # Deployment api-app (php-fpm) + Service api-app:9000
├── api-web.yaml          # Deployment api-web (nginx) + Service api-service:80
├── www-deployment.yaml   # Deployment www (Nuxt)
├── www-service.yaml      # Service для www
├── ingress.yaml          # Ingress для маршрутизации трафика
├── php/
│   └── Dockerfile        # php-fpm образ (Laravel), :9000
├── nginx/
│   ├── Dockerfile        # nginx образ (статика + proxy на php-fpm), :80
│   ├── default.conf      # Конфиг nginx для docker-compose (fastcgi → app:9000)
│   └── kube.conf         # Конфиг nginx для Kubernetes (fastcgi → api-app:9000)
└── README.md
```

## Образы

| Слой  | Dockerfile            | Экспонирует | Назначение              |
|-------|-----------------------|-------------|-------------------------|
| api   | `.kube/php/Dockerfile`    | 9000     | php-fpm (Laravel)       |
| web   | `.kube/nginx/Dockerfile`  | 80       | nginx (статика + proxy на php-fpm) |

Nginx-образ содержит копию приложения (папка `public/`), чтобы обслуживать
статику самостоятельно, PHP обрабатывается через FastCGI на сервис `api-app:9000`.

## Требования

- Kubernetes cluster (minikube, k3s, EKS, GKE, etc.)
- kubectl настроен и работает
- Docker/container runtime для сборки образов

## Развертывание

### 1. Применить манифесты

```bash
kubectl apply -f namespace.yaml
kubectl apply -f secret.yaml
kubectl apply -f configmap.yaml
kubectl apply -f postgres.yaml
kubectl apply -f api-php.yaml
kubectl apply -f api-web.yaml
kubectl apply -f www-deployment.yaml
kubectl apply -f www-service.yaml
kubectl apply -f ingress.yaml
```

### 2. Собрать и загрузить Docker образы

```bash
docker build -t home-store-api:latest -f .kube/php/Dockerfile .
docker build -t home-store-web:latest -f .kube/nginx/Dockerfile .
```

Для minikube:
```bash
minikube image load home-store-api:latest
minikube image load home-store-web:latest
```

Для k3s:
```bash
docker save home-store-api:latest | sudo k3s ctr images import -
docker save home-store-web:latest | sudo k3s ctr images import -
```

Для кластеров с registry — заменить `image:` в `api-php.yaml`/`api-web.yaml`
на `imagePullPolicy: IfNotPresent` и путь к своему registry.

### 3. Проверить статус

```bash
kubectl get pods,services -n home-store
kubectl get ingress -n home-store
kubectl logs -n home-store -l app=api-web
```

## Масштабирование

```bash
kubectl autoscale deployment api-app --cpu-percent=70 --min=2 --max=10 -n home-store
kubectl autoscale deployment api-web --cpu-percent=70 --min=2 --max=10 -n home-store
```

## Удаление

```bash
kubectl delete -f .
```

## Примечания

- **storage/app** в php-fpm поде смонтирован как `emptyDir` — данные не
  переживают перезапуск пода. Для persistent-upload-ов используйте PVC
  (предпочтительно ReadWriteMany) и смонтируйте его в оба пода (api-app и api-web).
- Миграции выполняются init-контейнером `migrate` в `api-php.yaml`.
- Hunspell-словари для postgres монтируются через `hostPath`
  (`/data/home-store/tsearch_data/`), т.к. файл `.dic` (~3.5MB) не влезает в ConfigMap.