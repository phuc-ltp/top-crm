{{-- Thẻ KPI dùng chung cho hồ sơ nhân viên và nhóm. $k: một dòng của EmployeeKpiService --}}
<div class="row g-3">
    <div class="col-6 col-lg-3"><x-stat-card icon="bi-cash-coin" tone="green" :label="__('Doanh thu đã thu')" :value="money($k['revenue'])"
        :sub="$k['achievement'] !== null ? __(':pct% chỉ tiêu (:target)', ['pct' => $k['achievement'], 'target' => money($k['target'])]) : __('Chưa đặt chỉ tiêu')" /></div>
    <div class="col-6 col-lg-3"><x-stat-card icon="bi-trophy" :label="__('Cơ hội thắng')" :value="$k['deals_won']" :sub="money($k['deals_value'])" /></div>
    <div class="col-6 col-lg-3"><x-stat-card icon="bi-check2-circle" tone="blue" :label="__('Việc hoàn thành')" :value="$k['tasks_done']"
        :sub="$k['on_time_rate'] !== null ? __(':pct% đúng hạn', ['pct' => $k['on_time_rate']]) : null" /></div>
    <div class="col-6 col-lg-3"><x-stat-card icon="bi-headset" tone="purple" :label="__('Ticket đã giải quyết')" :value="$k['tickets_resolved']" /></div>
</div>
