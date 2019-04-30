# Summary

<?php if ($readme ?? true) : ?>
----

* [必读](README.md)

----
<?php endif ?>

<?= $tree ?>

<?php if ($appendixes ?? false) : ?>
----

## 附录

<?php foreach ($appendixes as $appendix) : ?>
<?php extract($appendix); ?>
* [<?= $domain ?> - <?= $title ?>](<?= $path ?>)
<?php endforeach ?>

----
<?php endif ?>