# Сборка Docker-образов API (home-store)

Все команды выполнять из корня проекта:

```bash
cd /home/ewolf/prjs/home_store/api
```

## 1. PHP-FPM образ (контейнер API, Laravel)

```bash
docker build --progress=plain -t home-store-api:latest -f Dockerfile .
```

~15-20 минут (компиляция gd + composer install).
Тяжёлые шаги кэшируются в BuildKit, так что повторный запуск быстрее.

Debian-пакеты качаются с зеркала Яндекса по умолчанию. Для CI вне РФ —
вернуть официальные зеркала:

```bash
docker build \
  --build-arg DEBIAN_MIRROR=https://deb.debian.org/debian \
  --build-arg DEBIAN_SECURITY_MIRROR=https://deb.debian.org/debian-security \
  -t home-store-api:latest -f Dockerfile .
```

### Кэширование composer-пакетов

В `Dockerfile` composer-зависимости ставятся до копирования кода:
слой с `composer install` переиспользуется, пока `composer.json`/`composer.lock`
не изменились (любые правки кода его не трогают).

### Шрифты PDF (tc-lib-pdf-font)

Сгенерированные шрифты **закоммичены** в `resources/pdf/fonts` (~42МБ).
При сборке образа они копируются в `vendor/tecnickcom/tc-lib-pdf-font/target/fonts`
и в билде больше **не** скачивается `tc-font-mirror` и не запускается `make fonts`.

Если обновляется `tecnickcom/tc-lib-pdf-font` в `composer.lock` — шрифты нужно
перегенерировать и обновить `resources/pdf/fonts`:

```bash
cd vendor/tecnickcom/tc-lib-pdf-font && make fonts
```

затем скопировать результат:

```bash
rm -rf resources/pdf/fonts && mkdir -p resources/pdf
cp -a vendor/tecnickcom/tc-lib-pdf-font/target/fonts resources/pdf/fonts
```

Принудительно снести кэш (пересобрать зависимости):

```bash
docker build --no-cache -t home-store-api:latest -f Dockerfile .
```

### Починка «висит на codeload.github.com / tc-font-mirror»

Если docker-compose локально запускает старый шаг генерации шрифтов —
убедись, что `vendor/tecnickcom/tc-lib-pdf-font/target/fonts` существует
(это поправит `make -C vendor/tecnickcom/tc-lib-pdf-font fonts` один раз),
либо используй закоммиченные шрифты из `resources/pdf/fonts`.

## 2. Nginx образ (веб-слой, статика + proxy на php-fpm)

```bash
docker build -t home-store-web:latest -f Dockerfile.nginx .
```

## 3. Проверка (опционально)

```bash
docker images | grep home-store
```

## 4. Загрузка образов в k3s (опционально)

```bash
docker save home-store-api:latest | sudo k3s ctr images import -
docker save home-store-web:latest | sudo k3s ctr images import -
```

## Для minikube (опционально)

```bash
minikube image load home-store-api:latest
minikube image load home-store-web:latest
```