<?php /** 위젯: 배너 슬라이더 */
$slides = $config['slides'] ?? [];
$interval = (int)($config['interval'] ?? 3000);
$height = $config['height'] ?? '250px';
$sliderId = 'slider-' . $widget['id'];
if (empty($slides)) return;
?>
<div class="widget widget-slider" data-widget-id="<?= $widget['id'] ?>">
    <?php if (!empty($widget['title'])): ?><h3 class="widget-title"><?= nb_e($widget['title']) ?></h3><?php endif; ?>
    <div class="slider-wrap" id="<?= $sliderId ?>" style="height:<?= nb_e($height) ?>">
        <div class="slider-track">
            <?php foreach ($slides as $i => $slide): ?>
            <div class="slider-slide <?= $i === 0 ? 'active' : '' ?>">
                <?php if (!empty($slide['link'])): ?>
                    <a href="<?= nb_e($slide['link']) ?>" target="_blank"><img src="<?= nb_url($slide['image']) ?>" alt="<?= nb_e($slide['title'] ?? '') ?>"></a>
                <?php else: ?>
                    <img src="<?= nb_url($slide['image']) ?>" alt="<?= nb_e($slide['title'] ?? '') ?>">
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if (count($slides) > 1): ?>
        <div class="slider-dots">
            <?php for ($i = 0; $i < count($slides); $i++): ?>
                <span class="slider-dot <?= $i === 0 ? 'active' : '' ?>" onclick="goSlide('<?= $sliderId ?>',<?= $i ?>)"></span>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php if (count($slides) > 1): ?>
<script>
(function(){
    var id='<?= $sliderId ?>',idx=0,total=<?= count($slides) ?>;
    function go(n){
        idx=n;if(idx>=total)idx=0;if(idx<0)idx=total-1;
        var el=document.getElementById(id);
        el.querySelectorAll('.slider-slide').forEach(function(s,i){s.classList.toggle('active',i===idx)});
        el.querySelectorAll('.slider-dot').forEach(function(d,i){d.classList.toggle('active',i===idx)});
    }
    setInterval(function(){go(idx+1)},<?= $interval ?>);
    window['goSlide_<?= $widget['id'] ?>']=go;
})();
function goSlide(id,n){window['goSlide_'+id.split('-')[1]](n)}
</script>
<?php endif; ?>
