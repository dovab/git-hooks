#!/bin/bash

PWD=$(pwd)

bash vendor/dovab/coding-standard-rules/install-rulesets.sh

printf "Installing GIT Hooks...\n"
if [ ! -L $PWD/.git/hooks/post-checkout ] ; then
   printf "\tInstall post-checkout hook\n"

    if [ -f $PWD/.git/hooks/post-checkout ]; then
        mv $PWD/.git/hooks/post-checkout $PWD/.git/hooks/post-checkout.local
    fi

    ln -s $PWD/vendor/dovab/git-hooks/post-checkout $PWD/.git/hooks/post-checkout
fi

if [ ! -L $PWD/.git/hooks/post-merge ] ; then
   printf "\tInstall post-merge hook\n"

    if [ -f $PWD/.git/hooks/post-merge ]; then
        mv $PWD/.git/hooks/post-merge $PWD/.git/hooks/post-merge.local
    fi

    ln -s $PWD/vendor/dovab/git-hooks/post-merge $PWD/.git/hooks/post-merge
fi

if [ ! -L $PWD/.git/hooks/pre-commit ] ; then
   printf "\tInstall pre-commit hook\n"

    if [ -f $PWD/.git/hooks/pre-commit ]; then
        mv $PWD/.git/hooks/pre-commit $PWD/.git/hooks/pre-commit.local
    fi

    ln -s $PWD/vendor/dovab/git-hooks/pre-commit.php $PWD/.git/hooks/pre-commit
fi