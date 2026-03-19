<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Calendar;
use App\Models\Event;

return new class extends Migration
{
    public function up(): void
    {
        // 全社カレンダー（owner_type = 'company'）が複数ある場合の重複削除
        $companyCalendars = Calendar::where('owner_type', 'company')->get();
        
        if ($companyCalendars->count() > 1) {
            // 「全社カレンダー」という名前のカレンダーを優先的に残す
            $primaryCalendar = $companyCalendars->where('calendar_name', '全社カレンダー')->first();
            
            // 「全社カレンダー」がない場合は最初のものを使用
            if (!$primaryCalendar) {
                $primaryCalendar = $companyCalendars->first();
                // 名前を「全社カレンダー」に統一
                $primaryCalendar->update(['calendar_name' => '全社カレンダー']);
            }
            
            // 重複するカレンダーを処理
            $duplicateCalendars = $companyCalendars->where('calendar_id', '!=', $primaryCalendar->calendar_id);
            
            foreach ($duplicateCalendars as $duplicate) {
                // 重複カレンダーの予定を主カレンダーに移行
                Event::where('calendar_id', $duplicate->calendar_id)
                    ->update(['calendar_id' => $primaryCalendar->calendar_id]);
                
                // 重複カレンダーを削除
                $duplicate->delete();
            }
        } else if ($companyCalendars->count() === 1) {
            // 1つしかない場合は名前を「全社カレンダー」に統一
            $calendar = $companyCalendars->first();
            if ($calendar->calendar_name !== '全社カレンダー') {
                $calendar->update(['calendar_name' => '全社カレンダー']);
            }
        }
    }

    public function down(): void
    {
        // ロールバック処理は特に必要なし
        // （データの整合性を保つため、元に戻さない）
    }
};