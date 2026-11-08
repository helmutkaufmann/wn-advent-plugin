<?php Block::put('breadcrumb') ?>
    <ul>
        <li><a href="<?= Backend::url('mercator/advent/calendars') ?>">Advent calendars</a></li>
        <li><?= e($this->pageTitle) ?></li>
    </ul>
<?php Block::endPut() ?>

<?php if (!$this->fatalError): ?>
    <?= Form::open(['class' => 'layout']) ?>
        <div class="layout-row">
            <?= $this->formRender() ?>
        </div>
        <div class="form-buttons">
            <div class="loading-indicator-container">
                <button type="submit" data-request="onSave" data-request-data="redirect:0" data-hotkey="ctrl+s, cmd+s"
                    data-load-indicator="Saving..." class="btn btn-primary">Save</button>
                <button type="button" data-request="onSave" data-request-data="close:1" data-hotkey="ctrl+enter, cmd+enter"
                    data-load-indicator="Saving..." class="btn btn-default">Save and close</button>
                <button type="button" class="oc-icon-trash-o btn-icon danger pull-right"
                    data-request="onDelete" data-load-indicator="Deleting..."
                    data-request-confirm="Delete this calendar?"></button>
                <a href="<?= Backend::url('mercator/advent/calendars/export/' . $formModel->id) ?>"
                    class="btn btn-default oc-icon-download">Export ZIP</a>
                <button type="button" class="btn btn-default pull-right"
                    data-request="onDeleteWithImages" data-load-indicator="Deleting..."
                    data-request-confirm="Delete this calendar and its imported images (media library: advent/<?= e($formModel->year) ?>)? Images stored elsewhere are kept.">Delete with images</button>
                <span class="btn-text">or <a href="<?= Backend::url('mercator/advent/calendars') ?>">Cancel</a></span>
            </div>
        </div>
    <?= Form::close() ?>
<?php else: ?>
    <p class="flash-message static error"><?= e($this->fatalError) ?></p>
<?php endif ?>
