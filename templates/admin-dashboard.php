<?php
/**
 * Admin Dashboard Template
 * 
 * @var SmartLicenseServer\Admin\DashboardPage $this
 */

use SmartLicenseServer\Analytics\RepositoryAnalytics;

?>
<div class="smliser-dashboard-wrap">
    <h1>Smart License Server Dashboard</h1>

    <section class="smliser-dashboard-section">
        <h2>Overall Repository Stats</h2>
        <ul>
            <li>Total Apps Hosted: <strong><?php echo 42; // dummy ?></strong></li>
            <li>Total Plugins: <strong><?php echo 20; ?></strong></li>
            <li>Total Themes: <strong><?php echo 10; ?></strong></li>
            <li>Total Software: <strong><?php echo 12; ?></strong></li>
            <li>Total Downloads: <strong><?php echo 1523; ?></strong></li>
            <li>Total Client Accesses (Last 30 days): <strong><?php echo 456; ?></strong></li>
            <li>Active Installations (Last 30 days): <strong><?php echo 321; ?></strong></li>
        </ul>
    </section>

    <section class="smliser-dashboard-section">
        <h2>License Activity (Last 30 days)</h2>
        <table border="1" cellpadding="5">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Activations</th>
                    <th>Deactivations</th>
                    <th>Uninstallations</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Dummy data
                $license_activity = [
                    '2025-12-01' => ['activate'=>12,'deactivate'=>3,'uninstall'=>1],
                    '2025-12-02' => ['activate'=>9,'deactivate'=>2,'uninstall'=>0],
                    '2025-12-03' => ['activate'=>15,'deactivate'=>1,'uninstall'=>2],
                ];

                foreach( $license_activity as $date => $metrics ) : ?>
                    <tr>
                        <td><?php echo $date; ?></td>
                        <td><?php echo $metrics['activate']; ?></td>
                        <td><?php echo $metrics['deactivate']; ?></td>
                        <td><?php echo $metrics['uninstall']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="smliser-dashboard-section">
        <h2>Apps Maintained by Month</h2>
        <table border="1" cellpadding="5">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Plugin Count</th>
                    <th>Theme Count</th>
                    <th>Software Count</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $maintained_by_month = [
                    '2025-10' => ['plugin'=>3,'theme'=>2,'software'=>1],
                    '2025-11' => ['plugin'=>4,'theme'=>1,'software'=>2],
                    '2025-12' => ['plugin'=>2,'theme'=>3,'software'=>2],
                ];

                foreach( $maintained_by_month as $month => $counts ) : ?>
                    <tr>
                        <td><?php echo $month; ?></td>
                        <td><?php echo $counts['plugin']; ?></td>
                        <td><?php echo $counts['theme']; ?></td>
                        <td><?php echo $counts['software']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

</div>

<style>
.smliser-dashboard-wrap { font-family: sans-serif; padding: 20px; }
.smliser-dashboard-section { margin-bottom: 30px; }
.smliser-dashboard-section h2 { margin-bottom: 10px; }
.smliser-dashboard-section ul { list-style: none; padding-left: 0; }
.smliser-dashboard-section ul li { margin-bottom: 5px; }
</style>
