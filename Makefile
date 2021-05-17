build:
	docker-compose build app

shell:
	docker-compose up -d
	docker attach fregata_bundle_app
