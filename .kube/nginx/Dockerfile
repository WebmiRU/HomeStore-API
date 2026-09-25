FROM nginx:alpine

COPY . /var/www/html
RUN ln -sfn /var/www/html/storage/app/public /var/www/html/public/storage

COPY .kube/nginx/kube.conf /etc/nginx/templates/default.conf.template

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]