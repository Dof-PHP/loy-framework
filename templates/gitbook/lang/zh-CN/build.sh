#!/bin/bash

set -e

cd <?= $output ?>

<?php if ($versions ?? false) : ?>
<?php foreach($versions as $version) : ?>
cd <?= $version ?> && gitbook install
cd ..
<?php endforeach ?>

GIT_DIR='__site/.git'
if [ -d "$GIT_DIR" ]; then
    mv $GIT_DIR git-tmp 2>&1 > /dev/null
fi
rm -rf __site 2>&1 > /dev/null

<?php foreach($versions as $version) : ?>

gitbook build <?= $version ?> __site/<?= $version ?>

<?php endforeach ?>

cp index.html __site
if [ -d "git-tmp" ]; then
    mv git-tmp $GIT_DIR 2>&1 > /dev/null
fi

<?php if ($deploy ?? true) : ?>
if [ -d "$GIT_DIR" ]; then
    cd __site
    git commit -a -m 'auto updated'
    git push origin master
fi
<?php endif ?>
<?php endif ?>