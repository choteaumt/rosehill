<div class="menuBar">
    <ul><li><a href="<?= BASE_URL ?>/interments">Interments</a></li>
        <li><a href="<?= BASE_URL ?>/cemeteries">Cemeteries</a></li>
        <li><a href="<?= BASE_URL ?>/deeds">Deeds</a></li>
    <?php
        if (userIsAllowed('Users')) {
            echo "
            <li><a href=\"".BASE_URL."/people\">People</a></li>
            <li><a href=\"".BASE_URL."/users\">Users</a></li>
            ";
        }
    ?>
    </ul>
</div>
