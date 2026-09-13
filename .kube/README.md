# Kubernetes Configuration for Home Store

## Структура

```
.kube/
├── namespace.yaml          # Namespace для проекта
├── configmap.yaml          # Конфигурация (переменные окружения)
├── api-deployment.yaml     # Deployment для API (Laravel)
├── api-service.yaml        # Service для API
├── www-deployment.yaml     # Deployment для www (Nuxt)
├── www-service.yaml        # Service для www
├── ingress.yaml            # Ingress для маршрутизации трафика
└── README.md               # Этот файл
```

## Требования

- Kubernetes cluster (minikube, k3s, EKS, GKE, etc.)
- kubectl настроен и работает
- Docker/container runtime для сборки образов

## Развертывание

### 1. Применить манифесты

```bash
# Применить все манифесты
kubectl apply -f namespace.yaml
kubectl apply -f configmap.yaml
kubectl apply -f api-deployment.yaml
kubectl apply -f api-service.yaml
kubectl apply -f www-deployment.yaml
kubectl apply -f www-service.yaml
kubectl apply -f ingress.yaml

# Или все сразу
kubectl apply -f .
```

### 2. Собрать и загрузить Docker образы

```bash
# Для API
cd ../api
docker build -t home-store-api:latest .

# Для www
cd ../www
docker build -t home-store-www:latest .
```

### 3. Загрузить образы в кластер

Для minikube:
```bash
minikube image load home-store-api:latest
minikube image load home-store-www:latest
```

Для k3s:
```bash
docker save home-store-api:latest | sudo k3s ctr images import -
docker save home-store-www:latest | sudo k3s ctr images import -
```

Для кластеров с registry:
```bash
# Загрузить в registry
docker push your-registry/home-store-api:latest
docker push your-registry/home-store-www:latest

# Обновить imagePullPolicy в манифестах на Always
```

### 4. Проверить статус

```bash
# Проверить поды
kubectl get pods -n home-store

# Проверить сервисы
kubectl get services -n home-store

# Проверить ingress
kubectl get ingress -n home-store

# Логи подов
kubectl logs -n home-store -l app=home-store-api
kubectl logs -n home-store -l app=home-store-www
```

## Конфигурация

### Ingress

По умолчанию Ingress настроен на хост `home-store.local`. Для продакшена:

1. Замените `home-store.local` на ваш домен в `ingress.yaml`
2. Обновите annotation в зависимости от ingress controller (nginx, traefik, etc.)
3. Настройте TLS для HTTPS

### ConfigMap

Переменные окружения в `configmap.yaml`:
- `API_HOST` - hostname сервиса API
- `NUXT_PUBLIC_API_BASE_URL` - URL для Nuxt приложения

### Replicas

По умолчанию 2 реплики для каждого сервиса. Измените в `*-deployment.yaml`:
```yaml
spec:
  replicas: 3  # измените количество
```

## Масштабирование

```bash
# Автоматическое масштабирование (HPA)
kubectl autoscale deployment api-deployment --cpu-percent=70 --min=2 --max=10 -n home-store
kubectl autoscale deployment www-deployment --cpu-percent=70 --min=2 --max=10 -n home-store
```

## Удаление

```bash
kubectl delete -f .
```
