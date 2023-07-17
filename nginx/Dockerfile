FROM nginx:latest

#COPY ./nginx/nginx.conf /etc/nginx/nginx.conf
COPY default.conf /etc/nginx/conf.d/default.conf

COPY . /var/www/html

WORKDIR /var/www/html

EXPOSE 80