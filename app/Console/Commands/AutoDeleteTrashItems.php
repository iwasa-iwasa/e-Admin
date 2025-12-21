<?php

namespace App\Console\Commands;

use App\Models\TrashItem;
use App\Models\TrashAutoDeleteSetting;
use App\Models\TrashAutoDeleteLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoDeleteTrashItems extends Command
{
    protected $signature = 'trash:auto-delete';
    protected $description = '設定された期間を過ぎたゴミ箱アイテムを自動削除';

    public function handle()
    {
        $users = \App\Models\User::where('is_active', true)->get();
        $totalDeleted = 0;
        
        foreach ($users as $user) {
            $setting = TrashAutoDeleteSetting::getUserPeriod($user->id);
            
            if ($setting === 'disabled') {
                continue;
            }

            $cutoffDate = $this->getCutoffDate($setting);
            if (!$cutoffDate) {
                continue;
            }

            $trashItems = TrashItem::where('user_id', $user->id)
                ->where('deleted_at', '<=', $cutoffDate)
                ->get();
            
            if ($trashItems->isEmpty()) {
                TrashAutoDeleteLog::createLog($user->id, $setting, 0);
                continue;
            }

            $deletedCount = 0;
            
            DB::transaction(function () use ($trashItems, &$deletedCount) {
                foreach ($trashItems as $item) {
                    try {
                        $this->deleteItem($item);
                        $deletedCount++;
                    } catch (\Exception $e) {
                        Log::error('自動削除エラー: ' . $e->getMessage(), [
                            'item_id' => $item->id,
                            'item_type' => $item->item_type
                        ]);
                    }
                }
            });

            TrashAutoDeleteLog::createLog($user->id, $setting, $deletedCount);
            $totalDeleted += $deletedCount;
            $this->info("{$user->name}: {$deletedCount}件削除");
        }

        $this->info("自動削除完了: 合計{$totalDeleted}件を削除しました。");
        Log::info("自動削除実行: 合計{$totalDeleted}件削除");
    }

    private function getCutoffDate(string $setting): ?\Carbon\Carbon
    {
        $now = now();
        
        return match ($setting) {
            '1_minute' => $now->subMinute(),
            '1_month' => $now->subMonth(),
            '3_months' => $now->subMonths(3),
            '6_months' => $now->subMonths(6),
            '1_year' => $now->subYear(),
            default => null,
        };
    }

    private function deleteItem(TrashItem $item): void
    {
        if ($item->item_type === 'shared_note') {
            \App\Models\SharedNote::where('note_id', $item->item_id)->delete();
        } elseif ($item->item_type === 'reminder') {
            \App\Models\Reminder::where('reminder_id', $item->item_id)->delete();
        } elseif ($item->item_type === 'survey') {
            $survey = \App\Models\Survey::where('survey_id', $item->item_id)->first();
            if ($survey) {
                $survey->questions()->each(function ($question) {
                    $question->options()->delete();
                });
                $survey->questions()->delete();
                $survey->responses()->each(function ($response) {
                    $response->answers()->delete();
                });
                $survey->responses()->delete();
                $survey->delete();
            }
        } elseif ($item->item_type === 'event') {
            \App\Models\Event::withTrashed()->where('event_id', $item->item_id)->forceDelete();
        }

        $item->delete();
    }
}