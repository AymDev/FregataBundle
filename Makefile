build:
	docker-compose build app

start:
	docker-compose up -d

stop:
	docker-compose down

shell:
	docker exec -it fregata_bundle_app bash

implementation:
	sudo rm -rf ./_implementation
	docker exec -it fregata_bundle_app composer config --global --no-interaction allow-plugins.symfony/flex true
	docker exec -it fregata_bundle_app composer config --global --no-interaction allow-plugins.dealerdirect/phpcodesniffer-composer-installer true
	docker exec -it fregata_bundle_app composer config --global --no-interaction allow-plugins.phpstan/extension-installer true
	docker exec -it fregata_bundle_app composer create-project symfony/skeleton:"4.4.*" ./_implementation --no-progress --no-interaction
	docker exec -it fregata_bundle_app composer --working-dir=./_implementation config repositories.fregata_bundle path ../
	docker exec -it fregata_bundle_app composer --working-dir=./_implementation require --no-interaction --with-all-dependencies aymdev/fregata-bundle:"dev-main" debug maker:"^1.35" messenger migrations twig
	docker exec -it fregata_bundle_app sed -i -E 's|^DATABASE_URL=.*$$|DATABASE_URL="postgresql://root:root@postgres:5432/fregata_bundle_db"|' ./_implementation/.env
	docker exec -it fregata_bundle_app sed -i -E 's|^# (MESSENGER_TRANSPORT_DSN=doctrine://default)$$|\1|' ./_implementation/.env
	docker exec -it fregata_bundle_app sed -i -E 's|^(\s+)# (async.*)$$|\1\2|' ./_implementation/config/packages/messenger.yaml
	docker exec -it fregata_bundle_app sed -i -E "s|^(\s+)# '.*'(: async)$$|\1'Fregata\\\FregataBundle\\\Messenger\\\FregataMessageInterface'\2|" ./_implementation/config/packages/messenger.yaml
	docker exec -it fregata_bundle_app ./_implementation/bin/console doctrine:database:drop --force
	docker exec -it fregata_bundle_app ./_implementation/bin/console doctrine:database:create
	docker exec -it fregata_bundle_app ./_implementation/bin/console make:migration
	docker exec -it fregata_bundle_app ./_implementation/bin/console doctrine:migrations:migrate --no-interaction
	sudo chown -R $$USER ./_implementation
	cp ./tests/Fixtures/config/routes/fregata.yaml ./_implementation/config/routes/fregata.yaml
	cp ./tests/Fixtures/config/packages/fregata.yaml ./_implementation/config/packages/fregata.yaml
	cp -R ./tests/Fixtures/src/. ./_implementation/src
	docker exec -it fregata_bundle_app ./_implementation/bin/console assets:install

reinstall:
	docker exec -it fregata_bundle_app sed -i -E "s|^(\s+)('Fregata.+)$$|\1#\2|" ./_implementation/config/packages/messenger.yaml
	rm -f ./_implementation/config/routes/fregata.yaml
	rm -f ./_implementation/config/packages/fregata.yaml
	sed -i '/Fregata\\FregataBundle\\FregataBundle::class/d' ./_implementation/config/bundles.php
	docker exec -it fregata_bundle_app composer --working-dir=./_implementation remove aymdev/fregata-bundle
	docker exec -it fregata_bundle_app composer --working-dir=./_implementation require aymdev/fregata-bundle:"*"
	docker exec -it fregata_bundle_app sed -i -E "s|^(\s+)#('Fregata.+)$$|\1\2|" ./_implementation/config/packages/messenger.yaml
	cp ./tests/Fixtures/config/routes/fregata.yaml ./_implementation/config/routes/fregata.yaml
	cp ./tests/Fixtures/config/packages/fregata.yaml ./_implementation/config/packages/fregata.yaml
	docker exec -it fregata_bundle_app ./_implementation/bin/console assets:install

messenger-consume:
	docker exec -it fregata_bundle_app ./_implementation/bin/console messenger:consume async --limit=1 -vvv