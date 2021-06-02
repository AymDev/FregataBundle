build:
	docker-compose build app

start:
	docker-compose up -d

stop:
	docker-compose down

shell:
	docker exec -it fregata_bundle_app bash

implementation:
	rm -rf ./_implementation
	docker exec -it fregata_bundle_app composer create-project symfony/skeleton:"4.4.*" ./_implementation --no-progress
	docker exec -it fregata_bundle_app composer --working-dir=./_implementation config minimum-stability dev
	docker exec -it fregata_bundle_app composer --working-dir=./_implementation config repositories.fregata_bundle path ../
	docker exec -it fregata_bundle_app composer --working-dir=./_implementation require aymdev/fregata-bundle:"*"
	sudo chown -R $$USER ./_implementation
