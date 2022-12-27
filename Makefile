.PHONY: all
all:
	docker run \
		--rm \
		--name sandbox-satysfi \
		--mount type=bind,src=$$(pwd),dst=/work \
		sandbox-satysfi \
		satysfi /work/slide.saty

.PHONY: shell
shell:
	docker run \
		-it \
		--rm \
		--name sandbox-satysfi \
		--mount type=bind,src=$$(pwd),dst=/work \
		sandbox-satysfi \
		sh

.PHONY: build
build:
	docker build --tag sandbox-satysfi .

.PHONY: clean
clean:
	rm -f *.pdf *.satysfi-aux
