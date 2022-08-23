FROM ubuntu:20.04
LABEL org.opencontainers.image.authors="ezeasorekene@gmail.com"


RUN apt-get update -y && apt-get upgrade -y && \
       apt-get install -y --no-install-recommends apt-utils && \
       apt-get -y install sudo && \
       apt-get install -y ca-certificates apt-transport-https software-properties-common

RUN sudo add-apt-repository ppa:ondrej/php
RUN sudo apt-get install apache2 -y
RUN sudo apt-get update -y

## for apt to be noninteractive
ENV DEBIAN_FRONTEND noninteractive
ENV DEBCONF_NONINTERACTIVE_SEEN true

## preesed tzdata, update package index, upgrade packages and install needed software
RUN echo "tzdata tzdata/Areas select Africa" > /tmp/preseed.txt; \
    echo "tzdata tzdata/Zones/Africa select Lagos" >> /tmp/preseed.txt; \
    debconf-set-selections /tmp/preseed.txt && \
    apt-get update -y && \
    apt-get install -y tzdata

RUN sudo apt-get install php8.0-fpm libapache2-mod-fcgid -y && \
         sudo a2enmod proxy_fcgi setenvif && \
         sudo a2enconf php8.0-fpm

COPY php/ezeasorekene.conf /etc/apache2/sites-available/ezeasorekene.conf
RUN a2enmod rewrite
RUN a2ensite ezeasorekene.conf
RUN service apache2 restart
RUN rm -rf /var/www/html/
COPY . /var/www/html/
WORKDIR /var/www/html/
EXPOSE 80
# RUN chmod -R 755 /var/www/html/

# CMD /bin/bash sudo service start apache2

ENTRYPOINT ["/usr/sbin/apache2ctl", "-D", "FOREGROUND"]
