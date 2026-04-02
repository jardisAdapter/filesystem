<---stack-------->: ## -----------------------------------------------------------------------
start: ## Start all services and wait until ready
	$(DOCKER_COMPOSE) up -d --wait minio
	@echo "Creating test bucket..."
	@docker exec minio mc alias set local http://localhost:9000 minioadmin minioadmin 2>/dev/null
	@docker exec minio mc mb --ignore-existing local/testbucket 2>/dev/null || true
	@echo "All services are ready!"
.PHONY: start

stop: ## Stop and remove all containers
	@echo "Stopping and removing all containers..."
	$(DOCKER_COMPOSE) down --volumes --remove-orphans
	@echo "All containers stopped and removed."
.PHONY: stop

restart: stop start ## Restart all containers
.PHONY: restart

status: ## Show status of all containers
	@echo "Container status:"
	@$(DOCKER_COMPOSE) ps -a
.PHONY: status

logs: ## Show logs from all containers
	$(DOCKER_COMPOSE) logs -f
.PHONY: logs
