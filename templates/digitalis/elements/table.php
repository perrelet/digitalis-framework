<table <?= $attributes ?>>
    <?php if ($first_row): ?>
    <thead>
        <tr <?= $row_atts[0] ?? '' ?>>
            <?php if (($row = ($rows[0] ?? '')) && is_array($row)): ?>
                <?php foreach ($row as $j => $header): ?>
                    <th scope='col' <?= $col_atts[$j] ?? '' ?>><?= $header ?></th>
                <?php endforeach; ?>
            <?php else: ?>
                <?= $row ?? '' ?>
            <?php endif; ?>
        </tr>
    </thead>
    <?php endif; ?>
    <tbody>
        <?php if ($rows) foreach ($rows as $i => $row): ?>
            <?php if ($first_row && !$i) continue; ?>
            <?php if ($last_row  && ($i == count($rows) - 1)) continue; ?>
            <tr <?= $row_atts[$i] ?? '' ?>>
                <?php if (is_array($row)): ?>
                    <?php foreach ($row as $j => $cell): ?>
                        <?php if (($first_col && !$j) || ($last_col && ($j == (count($row) - 1)))): ?>
                            <th scope='row' <?= $col_atts[$j] ?? '' ?>><?= $cell ?></th>
                        <?php else: ?>
                            <td <?= $col_atts[$j] ?? '' ?>><?= $cell ?></td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?= $row ?>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
    <?php if ($last_row): ?>
    <tfoot>
        <tr <?= $row_atts[count($rows) - 1] ?? '' ?>>
            <?php if (($row = ($rows[count($rows) - 1] ?? '')) && is_array($row)): ?>
                <?php foreach ($row as $j => $header): ?>
                    <th scope='col' <?= $col_atts[$j] ?? '' ?>><?= $header ?></th>
                <?php endforeach; ?>
            <?php else: ?>
                <?= $row ?? '' ?>
            <?php endif; ?>
        </tr>
    </tfoot>
    <?php endif; ?>
</table>