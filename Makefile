all: node.patch.bz2 node.tar.bz2

clean:
	find . -name "*~" -print -exec rm \{\} \;
	rm -f node.patch node.patch.bz2 node.tar node.tar.bz2

node.tar: modules/node/*.inc.php modules/node/node.module
	tar cvf "$@" $^

node.patch: modules/node/*
	git diff --no-prefix origin/vendor6 modules/node > "$@"

%.bz2: %
	bzip2 -9 -c $^ > "$@"
