<?php
/**
 * Flash messages.
 *
 * @var callable $e
 * @var array<int,array{type:string,text:string}> $messages
 */
if (empty($messages)) {
    return;
}
?>
<div class="moloni-on__messages">
    <?php foreach ($messages as $message): ?>
        <div class="alert alert-<?= $e($message['type']) ?>" role="alert">
            <?= $e($message['text']) ?>
        </div>
    <?php endforeach; ?>
</div>
