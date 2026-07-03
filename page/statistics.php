<?php
// 統計ページ
// page/statistics.php
?>
<h2><?= $lang['statistics'] ?></h2>

<script>
    google.charts.load('current', {packages: ['corechart', 'bar']});
    google.charts.setOnLoadCallback(drawStacked);

    function drawStacked() {
        var data = google.visualization.arrayToDataTable([
            ['年度', 'ボイパ対決', '素材', { role: 'annotation' }],
            <?php
            $cacheFile = './cache/statistics.json';
            $maxCount = 0; // 最大値を格納する変数
            if (file_exists($cacheFile) && time() - filemtime($cacheFile) < 86400) {
                $data = json_decode(file_get_contents($cacheFile), true);
            } else {
                $data = [];
                $json = file_get_contents('./data/index.json');
                $videos = json_decode($json, true);
                foreach ($videos as $id => $video) {
                    $year = date('Y', $video['publishedAt']);
                    if (!isset($data[$year])) {
                        $data[$year] = ['vps' => 0, 'material' => 0];
                    }
                    if ($video['type'] === 'vps') {
                        $data[$year]['vps']++;
                    } elseif ($video['type'] === 'material') {
                        $data[$year]['material']++;
                    }
                }
                file_put_contents($cacheFile, json_encode($data));
            }

            foreach ($data as $counts) {
                $maxCount = max($maxCount, $counts['vps'] + $counts['material']);
            }

            foreach ($data as $year => $counts) {
                if ($counts['vps'] > 0 || $counts['material'] > 0) {
                    $total = (int)$counts['vps'] + (int)$counts['material'];
                    
                    $vps = (int)$counts['vps'];
                    $material = (int)$counts['material'];

                    echo "['" . htmlspecialchars($year) . "', " . $vps . ", " . $material . ", '" . $total . "'],";
                }
            }
            ?>
        ]);
        
        var maxCount = <?= (int)$maxCount ?>;
        var maxRounded = Math.ceil(maxCount / 100) * 100;

        var ticks = [];
        for (var i = 0; i <= maxRounded; i += 100) {
            ticks.push(i);
        }
        
        const rootStyles = getComputedStyle(document.documentElement);
        const textColor = rootStyles.getPropertyValue('--text-color').trim();
        const borderColor = rootStyles.getPropertyValue('--border-color').trim();

        const isDark = document.documentElement.getAttribute('theme') === 'dark';
        const barColor1 = isDark ? '#6769e6' : '#6a8dff'; // ボイパ対決
        const barColor2 = isDark ? '#c44f4f' : '#fb2d2d'; // 素材

        var options = {
            title: '年度別ボ対投稿数',
            titleTextStyle: { color: textColor },
            backgroundColor: 'transparent',
            chartArea: { width: '65%', backgroundColor: 'transparent' },
            isStacked: true,
            legend: {
                textStyle: { color: textColor }
            },
            hAxis: {
                title: '年度',
                textStyle: { color: textColor },
                titleTextStyle: { color: textColor },
                baselineColor: borderColor,
                gridlines: { color: borderColor }
            },
            vAxis: {
                title: '投稿数',
                textStyle: { color: textColor },
                titleTextStyle: { color: textColor },
                baselineColor: borderColor,
                gridlines: { color: borderColor },
                ticks: ticks,
                maxValue: maxRounded
            },
            colors: [barColor1, barColor2],
            annotations: {
                alwaysOutside: true,
                textStyle: {
                    fontSize: 12,
                    color: textColor
                }
            }
        };

        var chart = new google.visualization.ColumnChart(document.getElementById('chart_div'));
        chart.draw(data, options);
    }
</script>

<div id="chart_div" style="max-width: 850px; height: 700px;"></div>