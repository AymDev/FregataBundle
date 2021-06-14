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
	docker exec -it fregata_bundle_app composer create-project symfony/skeleton:"4.4.*" ./_implementation --no-progress
	docker exec -it fregata_bundle_app composer --working-dir=./_implementation config minimum-stability dev
	docker exec -it fregata_bundle_app composer --working-dir=./_implementation config repositories.fregata_bundle path ../
	docker exec -it fregata_bundle_app composer --working-dir=./_implementation require aymdev/fregata-bundle:"*"
	docker exec -it fregata_bundle_app composer --working-dir=./_implementation require maker messenger orm
	docker exec -it fregata_bundle_app sed -i -E 's|^DATABASE_URL=.*$$|DATABASE_URL="postgresql://root:root@postgres:5432/fregata_bundle_db"|' ./_implementation/.env
	docker exec -it fregata_bundle_app sed -i -E 's|^# (MESSENGER_TRANSPORT_DSN=doctrine://default)$$|\1|' ./_implementation/.env
	docker exec -it fregata_bundle_app sed -i -E 's|^(\s+)# (async.*)$$|\1\2|' ./_implementation/config/packages/messenger.yaml
	docker exec -it fregata_bundle_app sed -i -E "s|^(\s+)# '.*'(: async)$$|\1'Fregata\\\FregataBundle\\\Messenger\\\FregataMessageInterface'\2|" ./_implementation/config/packages/messenger.yaml
	docker exec -it fregata_bundle_app ./_implementation/bin/console doctrine:database:drop --force
	docker exec -it fregata_bundle_app ./_implementation/bin/console doctrine:database:create
	docker exec -it fregata_bundle_app ./_implementation/bin/console make:migration
	docker exec -it fregata_bundle_app ./_implementation/bin/console doctrine:migrations:migrate --no-interaction
	sudo chown -R $$USER ./_implementation

reinstall:
	docker exec -it fregata_bundle_app sed -i -E "s|^(\s+)('Fregata.+)$$|\1#\2|" ./_implementation/config/packages/messenger.yaml
	docker exec -it fregata_bundle_app composer --working-dir=./_implementation remove aymdev/fregata-bundle
	docker exec -it fregata_bundle_app composer --working-dir=./_implementation require aymdev/fregata-bundle:"*"
	docker exec -it fregata_bundle_app sed -i -E "s|^(\s+)#('Fregata.+)$$|\1\2|" ./_implementation/config/packages/messenger.yaml
