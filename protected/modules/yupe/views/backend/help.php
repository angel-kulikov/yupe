<?php
$this->breadcrumbs = array(
    Yii::t('YupeModule.yupe', 'Система') => array('settings'),
    Yii::t('YupeModule.yupe', 'Юпи!') => array('settings'),
    Yii::t('YupeModule.yupe','Помощь')
);
?>

<h1><?php echo Yii::t('YupeModule.yupe', 'Помощь'); ?></h1>

<br />

<p>
    <?php echo Yii::t('YupeModule.yupe','Вы используете Yii версии'); ?>
    <small class="label label-info" title="<?php echo Yii::getVersion(); ?>"><?php echo Yii::getVersion(); ?></small>,
    <?php echo CHtml::encode(Yii::app()->name); ?>
    <?php echo Yii::t('YupeModule.yupe', 'версии'); ?> <small class="label label-info" title="<?php echo Yii::app()->getModule('yupe')->version; ?>"><?php echo Yii::app()->getModule('yupe')->version; ?></small>,
    <?php echo Yii::t('YupeModule.yupe', 'php версии'); ?>
    <small class="label label-info" title="<?php echo phpversion(); ?>"><?php echo phpversion(); ?></small>
</p>

<br/>

<div class="alert">
    <p>
        <?php echo Yii::t('YupeModule.yupe', ' Юпи! разрабатывается и поддерживается командой энтузиастов, Вы можете использовать Юпи! и любую его часть <b>совершенно бесплатно</b>'); ?>
    </p>
    <?php echo CHtml::link(Yii::t('YupeModule.yupe', 'А вот здесь мы принимаем благодарности =)'), 'http://yupe.ru/site/page/view/help/?form=help', array('target' => '_blank')); ?>
    <p><p><b>
        <?php echo Yii::t('YupeModule.yupe', 'По вопросам коммерческой поддержки и разработки Вы всегда можете <a href="http://yupe.ru/feedback/contact/?form=help" target="_blank">написать нам</a> (<a href="http://yupe.ru/feedback/contact/?form=help" target="_blank">http://yupe.ru/feedback/contact</a>)'); ?>
    </b></p></p>
</div>

<br />

<p><?php echo Yii::t('YupeModule.yupe', 'Полезные ресурсы:');?></p>

<?php echo CHtml::link(Yii::t('YupeModule.yupe','Обязательно прочтите документацию Yii'),'http://yiiframework.ru/doc/guide/ru/index');?>
<br /><br />


<?php echo CHtml::link(Yii::t('YupeModule.yupe', 'Официальный сайт Юпи!'), 'http://yupe.ru/?form=help'); ?> - <?php echo Yii::t('YupeModule.yupe', 'заходите чаще!'); ?>

<br /><br />

<?php echo CHtml::link(Yii::t('YupeModule.yupe', 'Форум поддержки Юпи!'), 'http://yupe.ru/talk/?form=help'); ?> - <?php echo Yii::t('YupeModule.yupe', 'заходите поболтать!'); ?>

<br /><br />

<?php echo CHtml::link(Yii::t('YupeModule.yupe', 'Исходный код на Github'), 'http://github.com/yupe/yupe/'); ?> - <?php echo Yii::t('YupeModule.yupe', 'пришлите нам парочку пулл-реквестов, все только выиграют =)'); ?>

<br /><br />

<?php echo CHtml::link(Yii::t('YupeModule.yupe', 'Официальный твиттер Юпи!'), 'https://twitter.com/#!/YupeCms'); ?>  - <?php echo Yii::t('YupeModule.yupe', 'обязательно заффоловьте нас, мы не спамим =)'); ?>

<br /><br />

<?php echo Yii::t('YupeModule.yupe', 'Напишите нам на <a href="mailto:team@yupe.ru">team@yupe.ru</a>'); ?>  - <?php echo Yii::t('YupeModule.yupe', 'принимаем всякого рода коммерческие и любые предложения =)'); ?>

<br /><br />

<b><?php echo Yii::t('YupeModule.yupe','Команда разработчиков Юпи!'); ?></b>
