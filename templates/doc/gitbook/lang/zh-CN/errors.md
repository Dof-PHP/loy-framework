<!-- toc -->

# 接口错误码列表

<?php if ($errors[0] ?? false) : ?>
## 系统预设

| 错误码 | KEY | 描述 | 备注 |
| :--- | :--- | :--- | :--- |
<?php foreach (($errors[0] ?? []) as $key => $data) : ?>
| `<?= $data[0] ?? -1 ?>` | `<?= $key ?>` | <?= $data[1] ?? -1 ?> | <?= $data[2] ?? '-' ?> |
<?php endforeach ?>
<?php endif ?>

<?php if ($errors[1] ?? false) : ?>
## 领域自定义

<?php foreach (($errors[1] ?? []) as $domain => $errs) : ?>
### <?= \Dof\Framework\ConfigManager::getDomainByKey($domain, 'domain.title', $domain) ?>


| 错误码 | KEY | 描述 | 备注 |
| :--- | :--- | :--- | :--- |
<?php foreach ($errs as $key => $data) : ?>
| `<?= $data[0] ?? -1 ?>` | `<?= $key ?>` | <?= $data[1] ?? -1 ?> | <?= $data[2] ?? '-' ?> |
<?php endforeach ?>
<?php endforeach ?>

<?php endif ?>
