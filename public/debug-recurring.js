// ブラウザのコンソールで実行してください
// カレンダーページを開いた状態で実行

(async () => {
    const start = '2026-01-01';
    const end = '2026-01-31';
    
    console.log('=== 繰り返しイベント表示デバッグ ===');
    console.log(`期間: ${start} ～ ${end}`);
    
    try {
        const response = await fetch(`/calendar/events?start=${start}&end=${end}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        if (!response.ok) {
            console.error('API Error:', response.status, response.statusText);
            return;
        }
        
        const events = await response.json();
        console.log(`\n総イベント数: ${events.length}`);
        
        const recurringEvents = events.filter(e => e.isRecurring === true);
        console.log(`繰り返しイベント数: ${recurringEvents.length}`);
        
        if (recurringEvents.length > 0) {
            console.log('\n【繰り返しイベント一覧】');
            recurringEvents.forEach(e => {
                console.log(`  - ${e.start_date}: ${e.title} (ID: ${e.id}, originalEventId: ${e.originalEventId})`);
            });
        } else {
            console.warn('⚠️ 繰り返しイベントが0件です');
        }
        
        // FullCalendar に渡されているイベントを確認
        console.log('\n【FullCalendar イベント確認】');
        const fcApi = document.querySelector('.fc')?.fcApi;
        if (fcApi) {
            const fcEvents = fcApi.getEvents();
            console.log(`FullCalendar イベント数: ${fcEvents.length}`);
            const fcRecurring = fcEvents.filter(e => e.extendedProps?.isRecurring === true);
            console.log(`FullCalendar 繰り返しイベント数: ${fcRecurring.length}`);
        } else {
            console.log('FullCalendar API が見つかりません');
        }
        
    } catch (error) {
        console.error('エラー:', error);
    }
})();
