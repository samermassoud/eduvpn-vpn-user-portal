<?php declare(strict_types=1); ?>
<?php $this->layout('base', ['activeItem' => 'stats', 'pageTitle' => $this->t('Stats')]); ?>
<?php $this->start('content'); ?>
<h2><?=$this->t('Summary'); ?></h2>
<table class="tbl">
    <thead>
        <tr>
            <th><?=$this->t('Profile'); ?></th>
            <th><?=$this->t('Total Traffic'); ?></th>
            <th><?=$this->t('Total # Unique Users'); ?></th>
            <th><?=$this->t('Highest (Maximum) # Concurrent Connections'); ?></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($profileConfigList as $profileConfig): ?>
        <tr>
            <td title="<?=$this->e($profileConfig->profileId()); ?>"><?=$this->e($profileConfig->displayName()); ?></td>
            <?php if (array_key_exists($profileConfig->profileId(), $statsData)): ?>
                <td><?=$this->bth((int) $statsData[$profileConfig->profileId()]['total_traffic']); ?></td>
                <td><?=$this->e((string) $statsData[$profileConfig->profileId()]['unique_user_count']); ?></td>
                <td><span title="<?=$this->e((string) $statsData[$profileConfig->profileId()]['max_concurrent_connections_time']); ?>"><?=$this->e((string) $statsData[$profileConfig->profileId()]['max_concurrent_connections']); ?> (<?=$this->e((string) $maxConcurrentConnectionLimit[$profileConfig->profileId()]); ?>)</span></td>
            <?php else: ?>
                <td><em><?=$this->t('N/A'); ?></em></td>
                <td><em><?=$this->t('N/A'); ?></em></td>
                <td><em><?=$this->t('N/A'); ?></em></td>
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h2><?=$this->t('Profile Usage'); ?></h2>
<ul>
<?php foreach ($profileConfigList as $profileConfig): ?>
<?php if (array_key_exists($profileConfig->profileId(), $graphStats) && 0 !== count($graphStats[$profileConfig->profileId()]['date_list'])): ?>
        <li><a href="#<?=$this->e($profileConfig->profileId()); ?>"><?=$this->e($profileConfig->displayName()); ?></a></li>
<?php endif; ?>
<?php endforeach; ?>
</ul>

<?php foreach ($profileConfigList as $profileConfig): ?>
<?php if (array_key_exists($profileConfig->profileId(), $graphStats) && 0 !== count($graphStats[$profileConfig->profileId()]['date_list'])): ?>
<h3 id="<?=$this->e($profileConfig->profileId()); ?>"><?=$this->e($profileConfig->displayName()); ?></h3>
<!-- #users -->
<figure>
    <table class="stats">
        <tbody>
<?php for ($y = 0; $y < 25; ++$y): ?>
    <tr>
<?php if (0 === $y): ?>
    <th class="index" rowspan="25"><span><?=$this->e((string) $graphStats[$profileConfig->profileId()]['max_unique_user_count']); ?> <?=$this->t('Users'); ?></span></th>
<?php endif; ?>
<?php foreach ($graphStats[$profileConfig->profileId()]['date_list'] as $dayStr => $dayData): ?>
<?php if ($graphStats[$profileConfig->profileId()]['date_list'][$dayStr]['user_fraction'] >= 25 - $y): ?>
    <td>X</td>
<?php else: ?>
    <td></td>
<?php endif; ?>
<?php endforeach; ?>
    </tr>
<?php endfor; ?>
        </tbody>
        <tfoot>
            <tr>
                <th class="index"><span><?=$this->t('Date'); ?></span></th>
<?php foreach (array_keys($graphStats[$profileConfig->profileId()]['date_list']) as $i => $dayStr): ?>
        <th>
<?php if (0 === $i % 3): ?>
            <span><?=$dayStr; ?></span>
<?php endif; ?>
        </th>
<?php endforeach; ?>
            </tr>
        </tfoot>
    </table>
<figcaption>
            <?=$this->t('Number of unique users of the VPN service over the last month.'); ?>
</figcaption>
</figure>

<!-- #traffic -->
<figure>
    <table class="stats">
        <tbody>
<?php for ($y = 0; $y < 25; ++$y): ?>
    <tr>
<?php if (0 === $y): ?>
    <th class="index" rowspan="25"><span><?=$this->bth((int) $graphStats[$profileConfig->profileId()]['max_traffic_count']); ?></span></th>
<?php endif; ?>

<?php foreach ($graphStats[$profileConfig->profileId()]['date_list'] as $dayStr => $dayData): ?>
<?php if ($graphStats[$profileConfig->profileId()]['date_list'][$dayStr]['traffic_fraction'] >= 25 - $y): ?>
    <td>X</td>
<?php else: ?>
    <td></td>
<?php endif; ?>
<?php endforeach; ?>
    </tr>
<?php endfor; ?>
        </tbody>
        <tfoot>
            <tr>
                <th class="index"><span><?=$this->t('Date'); ?></span></th>
<?php foreach (array_keys($graphStats[$profileConfig->profileId()]['date_list']) as $i => $dayStr): ?>
        <th>
<?php if (0 === $i % 3): ?>
            <span><?=$dayStr; ?></span>
<?php endif; ?>
        </th>
<?php endforeach; ?>
            </tr>
        </tfoot>
    </table>
<figcaption>
            <?=$this->t('VPN traffic over the last month.'); ?>
</figcaption>
</figure>
<?php endif; ?>
<?php endforeach; ?>

<?php if (0 !== count($appUsage)): ?>
<h2><?=$this->t('Application Usage'); ?></h2>
<figure>
    <svg class="appUsage" xmlns="http://www.w3.org/2000/svg" viewBox="-1 -1 2 2" style="transform: rotate(-90deg)">
<?php foreach ($appUsage as $appInfo): ?>
        <path class="pieColor<?=$this->e((string) ($appInfo['slice_no'] + 1)); ?>" d="<?=$this->e($appInfo['path_data']); ?>"/>
<?php endforeach; ?>
    </svg>
<figcaption>
<?=$this->t('Distribution of unique users over the VPN applications.'); ?>
    <ul class="appUsage">
<?php foreach ($appUsage as $appInfo): ?>
        <li>
<?php if (null === $appInfo['client_id']): ?>
            <span title="<?=$this->e((string) $appInfo['client_count']); ?>" class="pieLegend pieColor<?=$this->e((string) ($appInfo['slice_no'] + 1)); ?>"><?=$this->e((string) $appInfo['client_count_rel_pct']); ?>%</span><?=$this->t('Manual Configuration Download'); ?>
<?php else: ?>
            <span title="<?=$this->e((string) $appInfo['client_count']); ?>" class="pieLegend pieColor<?=$this->e((string) ($appInfo['slice_no'] + 1)); ?>"><?=$this->e((string) $appInfo['client_count_rel_pct']); ?>%</span><?=$this->clientIdToDisplayName($appInfo['client_id']); ?>
<?php endif; ?>
        </li>
<?php endforeach; ?>
    </ul>
</figcaption>
</figure>
<?php endif; ?>
<?php $this->stop('content'); ?>
