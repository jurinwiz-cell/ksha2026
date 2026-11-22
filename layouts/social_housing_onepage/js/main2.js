document.addEventListener('DOMContentLoaded', function() {
    // ─── 필터 상태 관리 ───
    const filterState = {
        region: '',
        public_entity: '',
        supply_type: '',
        status: ''
    };

    // ─── 필터 셀렉트 변경 감시 ───
    document.querySelectorAll('[data-filter]').forEach(el => {
        el.addEventListener('change', function() {
            filterState[this.dataset.filter] = this.value;
            loadDashboardData(filterState);
        });
    });

    // ─── Ajax로 대시보드 데이터 로드 ───
    function loadDashboardData(filters) {
        const params = new URLSearchParams(Object.entries(filters));
        
        fetch('/index.php?module=housing&act=getDashboardData&' + params)
            .then(res => res.json())
            .then(data => {
                updateKPIs(data.kpi);
                updateCharts(data.charts);
            });
    }

    // ─── KPI 업데이트 ───
    function updateKPIs(kpi) {
        document.getElementById('totalHouseholds').textContent = 
            kpi.total_households.toLocaleString('ko-KR');
        document.getElementById('completionRate').textContent = kpi.completion_rate + '%';
        document.getElementById('publicRatio').textContent = kpi.public_ratio + '%';
        
        // 프로그레스 바
        document.getElementById('completionBar').style.width = kpi.completion_rate + '%';
        document.getElementById('publicBar').style.width = kpi.public_ratio + '%';
    }

    // ─── 차트 업데이트 ───
    function updateCharts(charts) {
        // 기존 차트 인스턴스 파괴
        if (window.typeChartInstance) window.typeChartInstance.destroy();
        if (window.regionChartInstance) window.regionChartInstance.destroy();
        if (window.yearChartInstance) window.yearChartInstance.destroy();

        // 유형별 Donut
        window.typeChartInstance = new Chart(
            document.getElementById('typeChart').getContext('2d'),
            {
                type: 'doughnut',
                data: {
                    labels: charts.type.labels,
                    datasets: [{
                        data: charts.type.data,
                        backgroundColor: ['#1e3a8a', '#d97706', '#059669', '#7c3aed', '#db2777'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'right', labels: { boxWidth: 10, font: { size: 11 } } }
                    },
                    cutout: '70%'
                }
            }
        );

        // 지역별 Bar (가로)
        window.regionChartInstance = new Chart(
            document.getElementById('regionChart').getContext('2d'),
            {
                type: 'bar',
                data: {
                    labels: charts.region.labels,
                    datasets: [{
                        label: '세대수',
                        data: charts.region.data,
                        backgroundColor: '#d97706',
                        borderRadius: 4
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false } },
                        y: { grid: { display: false } }
                    }
                }
            }
        );

        // 연도별 Line
        window.yearChartInstance = new Chart(
            document.getElementById('yearChart').getContext('2d'),
            {
                type: 'line',
                data: {
                    labels: charts.year.labels,
                    datasets: [{
                        label: '공급 세대수',
                        data: charts.year.data,
                        borderColor: '#1e3a8a',
                        backgroundColor: 'rgba(30, 58, 138, 0.1)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointBackgroundColor: '#1e3a8a'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false } },
                        y: { beginAtZero: true, grid: { color: '#f3f4f6' } }
                    }
                }
            }
        );
    }

    // 초기 로드
    loadDashboardData(filterState);
});