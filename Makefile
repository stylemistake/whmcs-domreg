# Makefile

APP_NAME := domreg
SHELL := /bin/bash

all: vendor

composer.phar:
	php -r "readfile('https://getcomposer.org/installer');" | php

vendor: composer.phar composer.json
	php composer.phar install --no-dev
	@touch vendor

clean:

distclean: clean
	@rm -rf composer.phar
	@rm -rf vendor
