# Summary

<?php if ($readme ?? true) : ?>
----

* [必读](README.md)

<?php if ($errors ?? false) : ?>
* [接口错误码列表](errors.md)
<?php endif ?>

<?php foreach ($appendixes['global'] ?? [] as $appendix) : ?>
<?php extract($appendix); ?>
* [<?= $title ?>](<?= $href ?>)
<?php endforeach ?>

----
<?php endif ?>

<?= $tree ?>

<?php if ($appendixes ?? false) : ?>
----

## 附录

<?php foreach ($appendixes['domain'] ?? [] as $appendix) : ?>
<?php extract($appendix); ?>
* [<?= $domain ?> - <?= $title ?>](<?= $href ?>)
<?php endforeach ?>

----
<?php endif ?>