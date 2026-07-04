BINARY  := stereodd
MODULE  := github.com/beepxtra/steroid-core4.0
GOFLAGS ?=

.PHONY: all build install test lint mod-tidy proto clean

all: build

## build: compile the stereodd binary to ./build/
build:
	@mkdir -p build
	go build $(GOFLAGS) -o build/$(BINARY) ./cmd/stereodd

## install: install stereodd to $(GOPATH)/bin
install:
	go install $(GOFLAGS) ./cmd/stereodd

## test: run all unit tests
test:
	go test -count=1 -timeout 10m ./...

## test-race: run tests with the race detector
test-race:
	go test -race -count=1 -timeout 10m ./...

## lint: run golangci-lint
lint:
	golangci-lint run --timeout 5m ./...

## lint-fix: run golangci-lint with auto-fix
lint-fix:
	golangci-lint run --fix --timeout 5m ./...

## mod-tidy: tidy and verify go modules
mod-tidy:
	go mod tidy
	go mod verify

## proto: generate protobuf bindings
## TODO: add buf generate once proto/ definitions are in place
proto:
	./scripts/protocgen.sh

## clean: remove build artefacts
clean:
	rm -rf build/

## help: print this message
help:
	@sed -n 's/^## //p' $(MAKEFILE_LIST) | column -t -s ':' | sort
