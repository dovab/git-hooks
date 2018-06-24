#!/bin/bash

PWD=$(pwd)

bash vendor/dovab/coding-standard-rules/install-rulesets.sh

echo "Installing GIT Hooks..."
if [ ! -L $PWD/.git/hooks/post-checkout ] ; then
    echo "\tInstall post-checkout hook"

    if [ -f $PWD/.git/hooks/post-checkout ]; then
        mv $PWD/.git/hooks/post-checkout $PWD/.git/hooks/post-checkout.local
    fi

    ln -s $PWD/vendor/dovab/git-hooks/post-checkout $PWD/.git/hooks/post-checkout
fi

if [ ! -L $PWD/.git/hooks/post-merge ] ; then
    echo "\tInstall post-merge hook"

    if [ -f $PWD/.git/hooks/post-merge ]; then
        mv $PWD/.git/hooks/post-merge $PWD/.git/hooks/post-merge.local
    fi

    ln -s $PWD/vendor/dovab/git-hooks/post-merge $PWD/.git/hooks/post-merge
fi

if [ ! -L $PWD/.git/hooks/pre-commit ] ; then
    echo "\tInstall pre-commit hook"

    if [ -f $PWD/.git/hooks/pre-commit ]; then
        mv $PWD/.git/hooks/pre-commit $PWD/.git/hooks/pre-commit.local
    fi

    ln -s $PWD/vendor/dovab/git-hooks/pre-commit.php $PWD/.git/hooks/pre-commit
fi