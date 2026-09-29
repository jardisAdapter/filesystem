<---stack-------->: ## -----------------------------------------------------------------------
start: ## Start all services and wait until ready
	$(DOCKER_COMPOSE) up -d --wait filesystem-s3
	@echo "Creating test bucket..."
	@code=$$(docker exec filesystem-s3 curl -s -o /dev/null -w '%{http_code}' -X PUT --user "$(or $(S3_TEST_ACCESS_KEY),s3testadmin):$(or $(S3_TEST_SECRET_KEY),s3testadmin)" --aws-sigv4 "aws:amz:$(or $(S3_TEST_REGION),us-east-1):s3" "http://localhost:9000/$(or $(S3_TEST_BUCKET),testbucket)"); \
	if [ "$$code" != "200" ] && [ "$$code" != "409" ]; then \
		echo "ERROR: test bucket creation failed (HTTP $$code, expected 200 or 409)"; exit 1; \
	fi
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
