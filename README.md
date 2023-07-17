Web Scraper applacation realised on Symfony6.2 framework

Application is available at 127.0.0.1:8080 after starting all docker containers.

Database:
Implemented database MySQL8 and is used for storing data about every company. In ScraperCommand after getting registration code of the company, either data will be added or updated

Cache:
Implemented Redis for storing session of the client based on his IP-address. Search results are rendering all of the companies, that were searched by the client during the last hour. It is realised so: ipAddress(key) - array of the registration codes (value).

Problems I was unable to solve:
    - connection with RabbitMQ and implementation
    - company's phone number is stored as the image. From some reasons I can't create directory and save there this images in directory public/images, because access is denied. It should be admitted, that when running command in the bash of the php container, then it works and images are stored.
    - CRUD is not realised due to the lack of the time

Scraping is realised in the ScraperCommand in the directory app/src/Command/ScraperCommand
Display is realised by the SearchController in the directory app/src/Controller/SearchController

Entity for storing company's data is Companies.

Dockerfile are written for Linux/Ubuntu