<table <?= $attributes ?>>
    <?php if ($headers['first_row']): ?>
    <thead>
        <tr>
            <?php if (is_array($rows[0])): ?>
                <?php if (isset($rows[0])) foreach ($rows[0] as $header): ?>
                    <th scope='col'><?= $header ?></th>
                <?php endforeach; ?>
            <?php else: ?>
                <?= $rows[0] ?? '' ?>
            <?php endif; ?>
        </tr>
    </thead>
    <?php endif; ?>
    <tbody>
        <?php if ($rows) foreach ($rows as $i => $row): ?>
            <?php if ($headers['first_row'] && !$i) continue; ?>
            <tr>
                <?php if (is_array($row)): ?>
                    <?php foreach ($row as $j => $cell): ?>
                        <?php if ($headers['first_col'] && !$j): ?>
                            <th scope='row'><?= $cell ?></th>
                        <?php else: ?>
                            <td><?= $cell ?></td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?= $row ?>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>