# Makefile for the WordPress plugin WordPress Memory Usage

# Default goal and help message for the Makefile
.DEFAULT_GOAL := help

plugin_name = WordPress Settings API
plugin_slug = wordpress-settings-api

# Help message for the Makefile
.PHONY: help
help::
	@echo "$(TEXT_BOLD)$(plugin_name)$(TEXT_BOLD_END) Makefile"
	@echo ""
	@echo "$(TEXT_BOLD)Usage:$(TEXT_BOLD_END)"
	@echo "  make [command]"
	@echo ""
	@echo "$(TEXT_BOLD)Commands:$(TEXT_BOLD_END)"

# Include the configurations
include .make/conf.d/*.mk
