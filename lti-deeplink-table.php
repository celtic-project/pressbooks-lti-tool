<?php
/*
 *  pressbooks-lti-tool - WordPress module to integrate LTI support with Pressbooks
 *  Copyright (C) 2023  Stephen P Vickers
 *
 *  Author: stephen@spvsoftwareproducts.com
 */

/**
 * Display a page of available books and sections in a filterable table and return the selection to the platform.
 */
// Prevent loading this file directly
defined('ABSPATH') || exit;

asort($titles);

if ($lti_tool_session['accept_multiple']) {
    $type = 'multi';
    $suffix = '[]';
} else {
    $type = 'single';
    $suffix = '';
}

$here = function($value) {
    $value = html_entity_decode($value);
    $value = str_replace('\'', '\\\'', $value);

    return $value;
};

$tag = function($tags, $site_id, $post_id) {
    $tag = '';
    if (!empty($tags[$site_id]) && !empty($tags[$site_id][$post_id])) {
        $tag = str_replace(',', ', ', $tags[$site_id][$post_id]->post_tags);
        $tag = str_replace('\'', '\\\'', $tag);
    }

    return $tag;
};

$allowed = array('html' => array('lang' => true), 'title' => array(), 'head' => array(),
    'link' => array('rel' => true, 'id' => true, 'href' => true, 'media' => true), 'style' => array(),
    'script' => array('src' => true), 'body' => array('onload' => true, 'class' => true), 'h1' => array(),
    'form' => array('id' => true, 'action' => true, 'method' => true, 'onsubmit' => true), 'p' => array(),
    'span' => array('href' => true, 'class' => true),
    'ul' => array('id' => true, 'class' => true), 'li' => array('class' => true),
    'a' => array('href' => true, 'class' => array(), 'onclick' => true),
    'input' => array('id' => true, 'type' => true, 'name' => true, 'value' => true, 'class' => true),
    'label' => array('for' => true), 'table' => array('id' => true, 'class' => true, 'width' => true),
    'thead' => array(), 'tbody' => array(), 'tfoot' => array(),
    'tr' => array(), 'th' => array(), 'td' => array());

echo('<!DOCTYPE html>' . "\n");
$html = '<html lang="en">' . "\n" .
    '<head>' . "\n" .
    '  <title>Select content</title>' . "\n" .
    $css .
    '  <link rel="stylesheet" href="https://cdn.datatables.net/2.0.2/css/dataTables.dataTables.min.css">' . "\n" .
    '  <link rel="stylesheet" href="https://cdn.datatables.net/select/2.0.0/css/select.dataTables.min.css">' . "\n" .
    '  <style>' . "\n" .
    '    body {' . "\n" .
    '      margin: 1em;' . "\n" .
    '    }' . "\n" .
    '    #content thead tr th {' . "\n" .
    '      vertical-align: bottom;' . "\n" .
    '    }' . "\n" .
    '    #content tbody tr td, #content tfoot tr th {' . "\n" .
    '      vertical-align: top;' . "\n" .
    '    }' . "\n" .
    '    input[type="checkbox"]:checked::before {' . "\n" .
    '      content: \'\';' . "\n" .
    '    }' . "\n";

echo(wp_kses($html, $allowed));

$html = '  </style>' . "\n" .
    '  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>' . "\n" .
    '  <script src="https://cdn.datatables.net/2.0.2/js/dataTables.min.js"></script>' . "\n" .
    '  <script src="https://cdn.datatables.net/select/2.0.0/js/dataTables.select.min.js"></script>' . "\n" .
    '  <script src="https://cdn.datatables.net/select/2.0.0/js/select.dataTables.min.js"></script>' . "\n" .
    '  <script>' . "\n";
echo(wp_kses($html, $allowed));
echo('    let bookColumn;' . "\n" .
 '    let partColumn;' . "\n" .
 '    let bookSelectH;' . "\n" .
 '    let table;' . "\n" .
 "\n" .
 '    function onLoad() {' . "\n" .
 '      table = new DataTable(\'#content\', {' . "\n" .
 '        layout: {' . "\n" .
 '          topEnd: null' . "\n" .
 '        },' . "\n" .
 '        columnDefs: [' . "\n" .
 '          {' . "\n" .
 '            render: DataTable.render.select(),' . "\n" .
 '            targets: 0' . "\n" .
 '          },' . "\n" .
 '          {' . "\n" .
 '            target: 1,' . "\n" .
 '            visible: false,' . "\n" .
 '            searchable: false' . "\n" .
 '          }' . "\n" .
 '        ],' . "\n" .
 '        select: {' . "\n" .
 '          style: \'' . $type . '\',' . "\n" .
 '          selector: \'td:first-child\'' . "\n" .
 '        },' . "\n" .
 '        columns: [' . "\n" .
 '          { title: \'&nbsp;\', ordering: true },' . "\n" .
 '          { title: \'&nbsp;\' },' . "\n" .
 '          { title: \'&nbsp;\', name: \'Book title\', ordering: false },' . "\n" .
 '          { title: \'&nbsp;\', name: \'Part\', ordering: false },' . "\n" .
 '          { title: \'&nbsp;\', name: \'Chapter\', ordering: false }');
if (!empty($tags)) {
    echo(',' . "\n" . '          { title: \'&nbsp;\', name: \'Keywords\', ordering: false }');
}
echo("\n");
echo('        ],' . "\n" .
 '        data: dataSet,' . "\n" .
 '        initComplete: function () {' . "\n" .
 '          bookColumn = this.api().column(\'Book title:name\');' . "\n" .
 '          bookColumn.header().addEventListener(\'click\', function(e) {' . "\n" .
 '            e.stopPropagation();' . "\n" .
 '          });' . "\n" .
 '          partColumn = this.api().column(\'Part:name\');' . "\n" .
 '          partColumn.header().addEventListener(\'click\', function(e) {' . "\n" .
 '            e.stopPropagation();' . "\n" .
 '          });' . "\n" .
 '          bookSelectH = document.createElement(\'select\');' . "\n" .
 '          bookSelectH.add(new Option(\'– All –\', \'\'));' . "\n" .
 '          bookColumn.header().replaceChildren(bookSelectH);' . "\n" .
 '          let bookSelectF = document.createElement(\'select\');' . "\n" .
 '          bookSelectF.add(new Option(\'– All –\', \'\'));' . "\n" .
 '          bookColumn.footer().replaceChildren(bookSelectF);' . "\n" .
 '          partColumn.header().replaceChildren();' . "\n" .
 '          partColumn.footer().replaceChildren();' . "\n" .
 '          bookSelectH.addEventListener(\'change\', function() {' . "\n" .
 '            bookSelectF.selectedIndex = bookSelectH.selectedIndex;' . "\n" .
 '            doBookFilter();' . "\n" .
 '          });' . "\n" .
 '          bookSelectF.addEventListener(\'change\', function() {' . "\n" .
 '            bookSelectH.selectedIndex = bookSelectF.selectedIndex;' . "\n" .
 '            doBookFilter();' . "\n" .
 '          });' . "\n" .
 '          bookColumn' . "\n" .
 '            .data()' . "\n" .
 '            .unique()' . "\n" .
 '            .sort()' . "\n" .
 '            .each(function (d, j) {' . "\n" .
 '              bookSelectH.add(new Option(d));' . "\n" .
 '              bookSelectF.add(new Option(d));' . "\n" .
 '            });' . "\n" .
 '          let chapterColumn = this.api().column(\'Chapter:name\');' . "\n" .
 '          chapterColumn.header().addEventListener(\'click\', function(e) {' . "\n" .
 '            e.stopPropagation();' . "\n" .
 '          });' . "\n" .
 '          let chapterInputH = document.createElement(\'input\');' . "\n" .
 '          chapterInputH.style.width = \'95%\';' . "\n" .
 '          chapterInputH.placeholder = \'Filter by chapter\';' . "\n" .
 '          chapterColumn.header().replaceChildren(chapterInputH);' . "\n" .
 '          chapterColumn.header().attributes.removeNamedItem(\'class\');' . "\n" .
 '          let chapterInputF = document.createElement(\'input\');' . "\n" .
 '          chapterInputF.style.width = \'95%\';' . "\n" .
 '          chapterInputF.placeholder = \'Filter by chapter\';' . "\n" .
 '          chapterColumn.footer().replaceChildren(chapterInputF);' . "\n" .
 '          chapterInputH.addEventListener(\'keyup\', function (e) {' . "\n" .
 '            chapterInputF.value = chapterInputH.value;' . "\n" .
 '            chapterColumn.search(chapterInputH.value, {smart: true}).draw();' . "\n" .
 '          });' . "\n" .
 '          chapterInputF.addEventListener(\'keyup\', function (e) {' . "\n" .
 '            chapterInputH.value = chapterInputF.value;' . "\n" .
 '            chapterColumn.search(chapterInputF.value, {smart: true}).draw();' . "\n" .
 '          });' . "\n");
if (!empty($tags)) {
    echo('          let keywordsColumn = this.api().column(\'Keywords:name\');' . "\n" .
    '          keywordsColumn.header().addEventListener(\'click\', function(e) {' . "\n" .
    '            e.stopPropagation();' . "\n" .
    '          });' . "\n" .
    '          let keywordsInputH = document.createElement(\'input\');' . "\n" .
    '          keywordsInputH.style.width = \'95%\';' . "\n" .
    '          keywordsInputH.placeholder = \'Filter by keywords\';' . "\n" .
    '          keywordsColumn.header().replaceChildren(keywordsInputH);' . "\n" .
    '          let keywordsInputF = document.createElement(\'input\');' . "\n" .
    '          keywordsInputF.style.width = \'95%\';' . "\n" .
    '          keywordsInputF.placeholder = \'Filter by keywords\';' . "\n" .
    '          keywordsColumn.footer().replaceChildren(keywordsInputF);' . "\n" .
    '          keywordsInputH.addEventListener(\'keyup\', function (e) {' . "\n" .
    '            keywordsInputF.value = keywordsInputH.value;' . "\n" .
    '            keywordsColumn.search(keywordsInputH.value, {smart: true}).draw();' . "\n" .
    '          });' . "\n" .
    '          keywordsInputF.addEventListener(\'keyup\', function (e) {' . "\n" .
    '            keywordsInputH.value = keywordsInputF.value;' . "\n" .
    '            keywordsColumn.search(keywordsInputF.value, {smart: true}).draw();' . "\n" .
    '          });' . "\n");
}
echo('        }' . "\n" .
 '      });' . "\n" .
 '    }' . "\n" .
 "\n" .
 '    function doBookFilter() {' . "\n" .
 '      bookColumn' . "\n" .
 '        .search(bookSelectH.value, {smart: true})' . "\n" .
 '        .draw();' . "\n" .
 '      let partSelectH = document.createElement(\'select\');' . "\n" .
 '      partSelectH.addEventListener(\'click\', function(e) {' . "\n" .
 '        e.stopPropagation();' . "\n" .
 '      });' . "\n" .
 '      partSelectH.add(new Option(\'– All –\', \'\'));' . "\n" .
 '      partColumn.header().replaceChildren(partSelectH);' . "\n" .
 '      let partSelectF = document.createElement(\'select\');' . "\n" .
 '      partSelectF.add(new Option(\'– All –\', \'\'));' . "\n" .
 '      partColumn.footer().replaceChildren(partSelectF);' . "\n" .
 '      let parts = [];' . "\n" .
 '      table.data()' . "\n" .
 '        .each(function (d, j, x) {' . "\n" .
 '          if ((d[2] === bookSelectH.value) && (parts.indexOf(d[3]) < 0)) {' . "\n" .
 '              parts.push(d[3]);' . "\n" .
 '          }' . "\n" .
 '        }' . "\n" .
 '      );' . "\n" .
 '      if (parts.length > 0) {' . "\n" .
 '        parts.sort();' . "\n" .
 '        parts.forEach(function(v, i, a) {' . "\n" .
 '          partSelectH.add(new Option(v));' . "\n" .
 '          partSelectF.add(new Option(v));' . "\n" .
 '        });' . "\n" .
 '        partSelectH.addEventListener(\'change\', function () {' . "\n" .
 '          partSelectF.selectedIndex = partSelectH.selectedIndex;' . "\n" .
 '          partColumn' . "\n" .
 '            .search(partSelectH.value, {smart: true})' . "\n" .
 '            .draw();' . "\n" .
 '        });' . "\n" .
 '        partSelectF.addEventListener(\'change\', function () {' . "\n" .
 '          partSelectH.selectedIndex = partSelectF.selectedIndex;' . "\n" .
 '          partColumn' . "\n" .
 '            .search(partSelectF.value, {smart: true})' . "\n" .
 '            .draw();' . "\n" .
 '        });' . "\n" .
 '      } else {' . "\n" .
 '        partSelectH.style.display = \'none\';' . "\n" .
 '        partSelectF.style.display = \'none\';' . "\n" .
 '      }' . "\n" .
 '      partColumn.search(\'\').draw();' . "\n" .
 '    }' . "\n" .
 "\n" .
 '    function doSubmit() {' . "\n" .
 '      let selected = table.rows( { selected: true } ).data();' . "\n" .
 '      let frm = document.getElementById(\'id_frmselect\');' . "\n" .
 '      selected.each(function(d, j) {' . "\n" .
 '        let input = document.createElement(\'input\');' . "\n" .
 '        input.type = \'hidden\';' . "\n" .
 '        input.name = \'section' . $suffix . '\';' . "\n" .
 '        input.value = d[1] + \'-\' + d[4];' . "\n" .
 '        frm.appendChild(input);' . "\n" .
 '      });' . "\n" .
 '    }' . "\n" .
 "\n" .
 '    const dataSet = [' . "\n");

$html = '';
//foreach ($books as $id => $book_structure) {
foreach ($titles as $id => $title) {
    $book_structure = $books[$id];
    if (!empty($book_structure['front-matter'])) {
        foreach ($book_structure['front-matter'] as $k => $v) {
            $html .= '      [\'\', \'' . $id . '.' . $v['ID'] . '\', \'' . $here($titles[$id]) . '\', \'Front Matter\', \'' . $here($v['post_title']) . '\'';
            if (!empty($tags)) {
                $html .= ', \'' . $tag($tags, $id, $v['ID']) . '\'';
            }
            $html .= '],' . "\n";
        }
    }
    foreach ($book_structure['part'] as $key => $value) {
        if (!empty($value['chapters'])) {
            foreach ($value['chapters'] as $k => $v) {
                $html .= '      [\'\', \'' . $id . '.' . $v['ID'] . '\', \'' . $here($titles[$id]) . '\', \'' . $here($value['post_title']) . '\', \'' . $here($v['post_title']) . '\'';
                if (!empty($tags)) {
                    $html .= ', \'' . $tag($tags, $id, $v['ID']) . '\'';
                }
                $html .= '],' . "\n";
            }
        }
    }
    if (!empty($book_structure['back-matter'])) {
        foreach ($book_structure['back-matter'] as $k => $v) {
            $html .= '      [\'\', \'' . $id . '.' . $v['ID'] . '\', \'' . $here($titles[$id]) . '\', \'Back Matter\', \'' . $here($v['post_title']) . '\'';
            if (!empty($tags)) {
                $html .= ', \'' . $tag($tags, $id, $v['ID']) . '\'';
            }
            $html .= '],' . "\n";
        }
    }
}
$html .= '    ];' . "\n";
echo($html);

$html = '  </script>' . "\n" .
    '</head>' . "\n" .
    '<body onload="onLoad();" class="wp-admin wp-core-ui admin-color-pb_colors">' . "\n" .
    '  <h1>Select content to be linked</h1>' . "\n" .
    '  <form id="id_frmselect" action="" method="post" onsubmit="return doSubmit();">' . "\n" .
    '    ' . wp_nonce_field(PRESSBOOKS_LTI_TOOL_PLUGIN_NAME . '-nonce', '_wpnonce', true, false) . "\n";

if (empty($books)) {
    $html .= '    <p>' . "\n" .
        '      There is no content available at this time.' . "\n" .
        '    </p>' . "\n";
} else {
    $html .= '    <p>' . "\n" .
        '      <input type="submit" class="button button-primary" value="Submit">' . "\n" .
        '    </p>' . "\n" .
        '    <table id="content" class="cell-border compact stripe order-column" width="100%">' . "\n" .
        '      <thead>' . "\n" .
        '        <tr>' . "\n" .
        '          <th></th>' . "\n" .
        '          <th></th>' . "\n" .
        '          <th></th>' . "\n" .
        '          <th></th>' . "\n" .
        '          <th></th>' . "\n";
    if (!empty($tags)) {
        $html .= '          <th></th>' . "\n";
    }
    $html .= '        </tr>' . "\n" .
        '      </thead>' . "\n" .
        '      <tbody>' . "\n" .
        '        <tr>' . "\n" .
        '          <td></td>' . "\n" .
        '          <td></td>' . "\n" .
        '          <td></td>' . "\n" .
        '          <td></td>' . "\n";
    if (!empty($tags)) {
        $html .= '          <td></td>' . "\n";
    }
    $html .= '        </tr>' . "\n" .
        '      </tbody>' . "\n" .
        '      <tfoot>' . "\n" .
        '        <tr>' . "\n" .
        '          <th></th>' . "\n" .
        '          <th></th>' . "\n" .
        '          <th></th>' . "\n" .
        '          <th></th>' . "\n" .
        '          <th></th>' . "\n";
    if (!empty($tags)) {
        $html .= '          <th></th>' . "\n";
    }
    $html .= '        </tr>' . "\n" .
        '      </tfoot>' . "\n" .
        '    </table>' . "\n";
}

$html .= '    <p>' . "\n" .
    '      <input type="submit" class="button button-primary" value="Submit">' . "\n" .
    '    </p>' . "\n" .
    '  </form>' . "\n" .
    '</body>' . "\n" .
    '</html>' . "\n";
echo(wp_kses($html, $allowed));
