#!/bin/bash

<?php if ($versions ?? false) : ?>
<?php foreach($versions as $version) : ?>
cd <?= $version ?> && gitbook install
cd ..
<?php endforeach ?>

rm -rf __site 2>&1 > /dev/null

<?php foreach($versions as $version) : ?>

gitbook build <?= $version ?> __site/<?= $version ?>

<?php endforeach ?>

mv index.html __site
<?php endif ?>