<?php
// AlertSystem.php
class AlertSystem {
    private $activeAlerts;
    private $recentlyResolved;
    private const SEVERITY_LEVELS = ['Minor', 'Moderate', 'Severe'];
    private const MAX_DISTANCE = 10; // miles
    private $conn;
    private $hazardTypes;
    private $alertFilters;

    public function __construct() {
        global $conn;
        if (!$conn instanceof mysqli) {
            throw new Exception("Database connection not established");
        }
        $this->conn = $conn;
        $this->hazardTypes = [
            'flooding' => ['Weather', 3],
            'traffic' => ['Traffic', 2],
            'pothole' => ['Infrastructure', 1],
            'accident' => ['Traffic', 3],
            'construction' => ['Infrastructure', 2],
            'weather' => ['Weather', 2]
        ];
        $this->alertFilters = [
            'severity' => [],
            'category' => [],
            'distance' => self::MAX_DISTANCE,
            'frequency' => 'immediate'
        ];
        $this->initializeDataFromDatabase();
    }

    private function initializeDataFromDatabase() {
        if (!$this->conn->ping()) {
            throw new Exception("Database connection lost");
        }

        $query = "SELECT * FROM hazard_reports ORDER BY reported_at DESC";
        $result = $this->conn->query($query);
        
        if (!$result) {
            throw new Exception("Database query failed: " . $this->conn->error);
        }

        if ($result->num_rows > 0) {
            $this->activeAlerts = [];
            while($row = $result->fetch_assoc()) {
                $hazardInfo = $this->hazardTypes[strtolower($row['hazard_type'])] ?? null;
                $this->activeAlerts[] = [
                    'id' => $row['id'],
                    'title' => "Hazard Report: {$row['address']}",
                    'severity' => $this->mapSeverity($hazardInfo[1] ?? $row['severity']),
                    'distance' => $this->calculateDistance(
                        $row['latitude'],
                        $row['longitude']
                    ),
                    'updated' => $this->formatTime($row['reported_at']),
                    'category' => $hazardInfo[0] ?? 'Other',
                    'hazard_type' => $row['hazard_type']
                ];
            }
            $result->close();
        } else {
            $this->activeAlerts = [];
        }
        
        $this->recentlyResolved = [];
    }

    private function mapSeverity($severityValue) {
        if (is_numeric($severityValue)) {
            switch ($severityValue) {
                case 1: return 'Minor';
                case 2: return 'Moderate';
                case 3: return 'Severe';
                default: return 'Unknown';
            }
        }
        return $severityValue;
    }

    private function getCategorizeHazard($hazardType) {
        $categories = [
            'flooding' => 'Weather',
            'traffic' => 'Traffic',
            'pothole' => 'Infrastructure',
            'accident' => 'Traffic',
            'construction' => 'Infrastructure',
            'weather' => 'Weather'
        ];
        return $categories[strtolower($hazardType)] ?? 'Other';
    }

    private function calculateDistance($latitude, $longitude) {
        return rand(1, self::MAX_DISTANCE);
    }

    public function formatTime($timeStr) {
        return htmlspecialchars($timeStr);
    }

    public function getSeverityColor($severity) {
        switch ($severity) {
            case 'Severe': return '#dc3545';
            case 'Moderate': return '#ffc107';
            case 'Minor': return '#28a745';
            default: return '#6c757d';
        }
    }

    public function getDistanceString($distance) {
        return sprintf('%s mile%s away',
            number_format($distance),
            $distance != 1 ? 's' : ''
        );
    }

    public function renderAlertList(bool $showResolved = false) {
        ob_start();
        $alerts = $this->getFilteredAlerts($showResolved);
        ?>
        <ul class="alerts-list">
            <?php foreach ($alerts as $alert): ?>
            <li class="alert-item" data-id="<?php echo htmlspecialchars($alert['id']); ?>">
                <div class="alert-header">
                    <h3 class="alert-title"><?php echo htmlspecialchars($alert['title']); ?></h3>
                    <span class="alert-category"><?php echo htmlspecialchars($alert['category']); ?></span>
                </div>
                <div class="alert-content">
                    <div class="severity-indicator"
                        style="background-color: <?php echo htmlspecialchars($this->getSeverityColor($alert['severity'])); ?>">
                        <?php echo htmlspecialchars($alert['severity']); ?>
                    </div>
                    <div class="alert-meta">
                        <?php if (!$showResolved): ?>
                            <span class="distance"><?php echo htmlspecialchars($this->getDistanceString($alert['distance'])); ?></span>
                            <time datetime="<?php echo date('Y-m-d H:i:s'); ?>">
                                <?php echo htmlspecialchars($alert['updated']); ?>
                            </time>
                        <?php else: ?>
                            <time datetime="<?php echo date('Y-m-d H:i:s'); ?>">
                                <?php echo htmlspecialchars($alert['resolved']); ?>
                            </time>
                        <?php endif; ?>
                    </div>
                    <div class="alert-actions">
                        <button class="btn-details">Details</button>
                        <?php if (!$showResolved): ?>
                            <button class="btn-dismiss" data-id="<?php echo htmlspecialchars($alert['id']); ?>">Dismiss</button>
                        <?php endif; ?>
                    </div>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php
        return ob_get_clean();
    }

    public function renderPreferences() {
        ob_start();
        ?>
        <section class="alert-preferences">
            <h2>Alert Preferences</h2>
            <form id="preferences-form" class="preferences-form">
                <div class="pref-section">
                    <h3>Notification Range</h3>
                    <div class="range-slider">
                        <input type="range"
                               id="distance-range"
                               min="1"
                               max="<?php echo self::MAX_DISTANCE; ?>"
                               value="<?php echo $this->alertFilters['distance']; ?>"
                               aria-label="Set notification distance range">
                        <span class="range-value"><?php echo $this->alertFilters['distance']; ?> miles</span>
                    </div>
                </div>
                <div class="pref-section">
                    <h3>Alert Types</h3>
                    <div class="alert-categories">
                        <?php foreach ($this->getCategories() as $category): ?>
                            <label class="checkbox-container">
                                <input type="checkbox"
                                       name="categories[]"
                                       value="<?php echo htmlspecialchars($category); ?>"
                                       <?php echo in_array($category, $this->alertFilters['category']) ? 'checked' : ''; ?>>
                                <?php echo htmlspecialchars($category); ?>
                                <span class="checkmark"></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="pref-section">
                    <h3>Alert Frequency</h3>
                    <select id="frequency-select" aria-label="Select alert frequency">
                        <option value="immediate" <?php echo $this->alertFilters['frequency'] === 'immediate' ? 'selected' : ''; ?>>
                            Immediate
                        </option>
                        <option value="hourly" <?php echo $this->alertFilters['frequency'] === 'hourly' ? 'selected' : ''; ?>>
                            Hourly Summary
                        </option>
                        <option value="daily" <?php echo $this->alertFilters['frequency'] === 'daily' ? 'selected' : ''; ?>>
                            Daily Digest
                        </option>
                    </select>
                </div>
                <div class="pref-section">
                    <h3>Severity Levels</h3>
                    <div class="severity-levels">
                        <?php foreach (self::SEVERITY_LEVELS as $severity): ?>
                            <label class="checkbox-container">
                                <input type="checkbox"
                                       name="severity[]"
                                       value="<?php echo htmlspecialchars($severity); ?>"
                                       <?php echo in_array($severity, $this->alertFilters['severity']) ? 'checked' : ''; ?>>
                                <span class="severity-indicator"
                                      style="background-color: <?php echo htmlspecialchars($this->getSeverityColor($severity)); ?>">
                                    <?php echo htmlspecialchars($severity); ?>
                                </span>
                                <span class="checkmark"></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>
        </section>
        <?php
        return ob_get_clean();
    }

    public function updatePreferences(array $preferences) {
        $this->alertFilters = array_merge($this->alertFilters, $preferences);
    }

    public function getCategories() {
        $categories = array_unique(array_column(
            array_merge($this->activeAlerts, $this->recentlyResolved),
            'category'
        ));
        sort($categories);
        return $categories;
    }

    public function getFilteredAlerts(bool $showResolved = false) {
        $alerts = $showResolved ? $this->recentlyResolved : $this->activeAlerts;
        
        if (!empty($this->alertFilters['severity'])) {
            $alerts = array_filter($alerts, function($alert) {
                return in_array($alert['severity'], $this->alertFilters['severity']);
            });
        }

        if (!empty($this->alertFilters['category'])) {
            $alerts = array_filter($alerts, function($alert) {
                return in_array($alert['category'], $this->alertFilters['category']);
            });
        }

        $alerts = array_filter($alerts, function($alert) {
            return $alert['distance'] <= $this->alertFilters['distance'];
        });

        return $alerts;
    }

    public function getActiveAlerts() {
        return $this->getFilteredAlerts();
    }

    public function getRecentlyResolved() {
        return $this->getFilteredAlerts(true);
    }

    public function addAlert($alert) {
        $this->activeAlerts[] = $alert;
    }

    public function resolveAlert($alertId) {
        foreach ($this->activeAlerts as $key => $alert) {
            if ($alert['id'] === $alertId) {
                $this->recentlyResolved[] = array_merge($alert, [
                    'resolved' => $this->formatTime('just now')
                ]);
                unset($this->activeAlerts[$key]);
                break;
            }
        }
    }
}