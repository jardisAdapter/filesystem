<---stack-------->: ## -----------------------------------------------------------------------
start: ## Start all services and wait until ready
	$(DOCKER_COMPOSE) up -d --wait filesystem-s3
	@echo "Creating test bucket..."
	@docker exec filesystem-s3 curl -fsS -X PUT --user s3testadmin:s3testadmin --aws-sigv4 "aws:amz:us-east-1:s3" http://localhost:9000/testbucket >/dev/null 2>&1 || true
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
