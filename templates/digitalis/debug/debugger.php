<digitalis-debugger-chip data-index="<?= $index ?>">
    🤖 <?= $title ?>
</digitalis-debugger-chip>
<digitalis-debugger
    <?= $open ? 'open' : '' ?>
    data-closable="<?= $closable ?>"
    data-index="<?= $index ?>"
    style="--index: <?= $index ?>; z-index: calc(2147483647 - var(--index)) !important;"
>
    <header>
        <?php if ($closable): ?>
            <button onclick="this.closest(`digitalis-debugger`).toggle();"><span></span></button>
            <button onclick="this.closest(`digitalis-debugger`).collapseToggle();"><span></span></button>
        <?php endif; ?>
        <button onclick="this.closest(`digitalis-debugger`).scrollTop();"><span></span></button>
        <button onclick="this.closest(`digitalis-debugger`).scrollPrev();"><span></span></button>
        <button onclick="this.closest(`digitalis-debugger`).scrollNext();"><span></span></button>
        <button onclick="this.closest(`digitalis-debugger`).scrollBottom();"><span></span></button>
        <button onclick="location.reload();"><span></span></button>
    </header>
    <main>
        <info>
            <h1><?= $title ?> 🤖</h1>
            <path><?= $debug_path ?></path>
        </info>
        <div backtrace>
            <label onclick="this.nextElementSibling.toggleAttribute(`open`);">Debug Backtrace [<?= count($backtrace) ?> Frames]</label>
            <code>
                <?= $bt_html ?>
            </code>
        </div>
        <?php foreach ($values as $i => $value) {
            Digitalis\Debug_Code_Block::render([
                'label' => $arg_names[$i] ?? false,
                'code'  => $value,
            ]);
        } ?>
    </main>
</digitalis-debugger>