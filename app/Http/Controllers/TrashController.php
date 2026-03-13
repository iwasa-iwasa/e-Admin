<?php

namespace App\Http\Controllers;

use App\Models\TrashItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class TrashController extends Controller
{
    /**
     * Display the trash page.
     *
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        try {
            \Log::info('TrashController index called for user: ' . Auth::id());
            
            $user = Auth::user();
            $departmentFilter = $request->query('department_filter', 'all');
            
            \Log::info('TrashController: departmentFilter = ' . $departmentFilter);
            \Log::info('TrashController: user role_type = ' . $user->role_type);
            \Log::info('TrashController: user department_id = ' . $user->department_id);
            
            $query = TrashItem::query();
            
            // 基本的には自分が削除したアイテムまたは自分の部署のアイテムを表示
            if ($user->role_type === 'company_admin') {
                // 全社管理者は全てのアイテムを見ることができる
                // フィルターに応じて絞り込み
                if ($departmentFilter === 'all') {
                    // 全て表示
                    \Log::info('TrashController: Showing all items for company admin');
                } elseif ($departmentFilter === 'public') {
                    $query->where('visibility_type', 'public');
                    \Log::info('TrashController: Filtering public items for company admin');
                } elseif (str_starts_with($departmentFilter, 'dept_')) {
                    $deptId = (int)str_replace('dept_', '', $departmentFilter);
                    $query->where('owner_department_id', $deptId);
                    \Log::info('TrashController: Filtering department ' . $deptId . ' items for company admin');
                }
            } else {
                // 部署管理者またはメンバーの場合
                if ($departmentFilter === 'all') {
                    // 自分が削除したアイテムと自分の部署のアイテムを表示
                    $query->where(function($q) use ($user) {
                        $q->where('user_id', $user->id) // 自分が削除したアイテム
                          ->orWhere('owner_department_id', $user->department_id); // 自分の部署のアイテム
                    });
                    \Log::info('TrashController: Showing all accessible items for department user');
                } elseif ($departmentFilter === 'public') {
                    // 全社公開アイテムのみ
                    $query->where('visibility_type', 'public');
                    \Log::info('TrashController: Filtering public items for department user');
                } elseif ($departmentFilter === 'mine') {
                    // 自分が削除したアイテムのみ
                    $query->where('user_id', $user->id);
                    \Log::info('TrashController: Filtering own items for department user');
                } elseif (str_starts_with($departmentFilter, 'dept_')) {
                    // 特定の部署のアイテムのみ（自分の部署の場合のみ表示可能）
                    $deptId = (int)str_replace('dept_', '', $departmentFilter);
                    if ($deptId === $user->department_id) {
                        // 自分の部署の場合のみ表示
                        $query->where('owner_department_id', $deptId);
                        \Log::info('TrashController: Filtering own department ' . $deptId . ' items for department user');
                    } else {
                        // 他の部署は表示しない
                        $query->whereRaw('1 = 0'); // 空の結果を返す
                        \Log::info('TrashController: Blocking access to other department ' . $deptId . ' for department user');
                    }
                }
            }
            
            $trashItems = $query->with('user')
                ->orderBy('deleted_at', 'desc')
                ->get();
                
            \Log::info('TrashController: Found ' . $trashItems->count() . ' trash items');
                
            \Log::info('Found trash items: ' . $trashItems->count());
            
            $mappedItems = $trashItems->map(function ($item) {
                $description = '';
                $creatorName = null;
                
                // アイテムタイプに応じて詳細情報を取得
                if ($item->item_type === 'shared_note') {
                    $note = \App\Models\SharedNote::with('author')->where('note_id', $item->item_id)->first();
                    $description = $note ? $note->content : '';
                    $creatorName = $note && $note->author ? $note->author->name : null;
                } elseif ($item->item_type === 'survey') {
                    $survey = \App\Models\Survey::with('creator')->where('survey_id', $item->item_id)->first();
                    $description = $survey ? $survey->description : '';
                    $creatorName = $survey && $survey->creator ? $survey->creator->name : null;
                } elseif ($item->item_type === 'reminder') {
                    $reminder = \App\Models\Reminder::with('user')->where('reminder_id', $item->item_id)->first();
                    $description = $reminder ? $reminder->description : '';
                    $creatorName = $reminder && $reminder->user ? $reminder->user->name : null;
                } elseif ($item->item_type === 'event') {
                    $event = \App\Models\Event::withTrashed()->with('creator')->where('event_id', $item->item_id)->first();
                    $description = $event ? $event->description : '';
                    $creatorName = $event && $event->creator ? $event->creator->name : null;
                }
                
                $mapped = [
                    'id' => (string)$item->id,
                    'type' => $item->item_type,
                    'title' => $item->original_title,
                    'description' => $description,
                    'deletedAt' => $item->deleted_at->format('Y-m-d H:i'),
                    'item_id' => (string)$item->item_id,
                    'permanent_delete_at' => $item->permanent_delete_at ? $item->permanent_delete_at->format('Y-m-d H:i') : null,
                    'creatorName' => $item->creator_name ?: $creatorName,
                    'deletedBy' => $item->user ? $item->user->name : '',
                    'isShared' => $item->is_shared,
                    'owner_department_id' => $item->owner_department_id,
                    'is_mine' => $item->user_id === Auth::id(),
                ];
                return $mapped;
            });
            
            \Log::info('Mapped items: ' . $mappedItems->toJson());
            
        } catch (\Exception $e) {
            \Log::error('TrashController error: ' . $e->getMessage());
            $mappedItems = collect([]);
            $departmentFilter = 'all';
        }

        $departments = \App\Models\Department::where('is_active', true)->orderBy('name')->get();

        return Inertia::render('Trash', [
            'trashItems' => $mappedItems,
            'highlight' => $request->query('highlight'),
            'currentDepartmentFilter' => $departmentFilter,
            'userDepartmentId' => Auth::user()->department_id,
            'departments' => $departments,
        ]);
    }

    /**
     * Restore an item from trash.
     */
    public function restore(Request $request, $id)
    {
        try {
            \Log::info('TrashController restore called with ID: ' . $id . ' for user: ' . Auth::id());
            
            $trashItem = TrashItem::where('id', $id)
                ->where('user_id', Auth::id())
                ->first();
                
            if (!$trashItem) {
                \Log::error('TrashItem not found for ID: ' . $id);
                return back()->with('error', 'アイテムが見つかりません。');
            }
            
            \Log::info('Found trash item: ' . $trashItem->item_type . ' - ' . $trashItem->original_title);

            if ($trashItem->item_type === 'shared_note') {
                \DB::transaction(function () use ($trashItem) {
                    // メモを復元
                    $note = \App\Models\SharedNote::findOrFail($trashItem->item_id);
                    $note->update([
                        'is_deleted' => false,
                        'deleted_at' => null,
                    ]);
                    
                    // ゴミ箱テーブルから削除
                    $trashItem->delete();
                });
                
                \Log::info('Successfully restored shared note');
                return back()->with('success', 'メモを復元しました。');
            }
            
            if ($trashItem->item_type === 'reminder') {
                \DB::transaction(function () use ($trashItem) {
                    // リマインダーを復元
                    $reminder = \App\Models\Reminder::findOrFail($trashItem->item_id);
                    $reminder->update([
                        'completed' => false,
                        'completed_at' => null,
                    ]);
                    
                    // ゴミ箱テーブルから削除
                    $trashItem->delete();
                });
                
                \Log::info('Successfully restored reminder');
                return back()->with('success', 'リマインダーを復元しました。');
            }
            
            if ($trashItem->item_type === 'survey') {
                \DB::transaction(function () use ($trashItem) {
                    // アンケートを復元
                    $survey = \App\Models\Survey::where('survey_id', $trashItem->item_id)->firstOrFail();
                    $survey->update([
                        'is_deleted' => false,
                        'deleted_at' => null,
                    ]);
                    
                    // ゴミ箱テーブルから削除
                    $trashItem->delete();
                });
                
                \Log::info('Successfully restored survey');
                return back()->with('success', 'アンケートを復元しました。');
            }
            
            if ($trashItem->item_type === 'event') {
                \DB::transaction(function () use ($trashItem) {
                    // イベントを復元
                    $event = \App\Models\Event::withTrashed()->where('event_id', $trashItem->item_id)->firstOrFail();
                    $event->restore();
                    
                    // ゴミ箱テーブルから削除
                    $trashItem->delete();
                });
                
                \Log::info('Successfully restored event');
                return back()->with('success', 'イベントを復元しました。');
            }

            return back()->with('error', 'サポートされていないアイテムタイプです。');
        } catch (\Exception $e) {
            \Log::error('TrashController restore error: ' . $e->getMessage());
            return back()->with('error', '復元に失敗しました。');
        }
    }

    /**
     * Restore multiple items from trash.
     */
    public function restoreMultiple(Request $request)
    {
        try {
            $items = $request->input('items', []);
            \Log::info('TrashController restoreMultiple called with items: ' . json_encode($items));
            
            if (empty($items)) {
                return back()->with('error', '復元するアイテムがありません。');
            }
            
            $restoredCount = 0;
            
            \DB::transaction(function () use ($items, &$restoredCount) {
                foreach ($items as $itemData) {
                    $trashItem = TrashItem::where('id', $itemData['id'])
                        ->where('user_id', Auth::id())
                        ->first();
                        
                    if (!$trashItem) {
                        \Log::warning('TrashItem not found for ID: ' . $itemData['id']);
                        continue;
                    }
                    
                    if ($trashItem->item_type === 'shared_note') {
                        $note = \App\Models\SharedNote::find($trashItem->item_id);
                        if ($note) {
                            $note->update([
                                'is_deleted' => false,
                                'deleted_at' => null,
                            ]);
                            $trashItem->delete();
                            $restoredCount++;
                        }
                    }
                    
                    if ($trashItem->item_type === 'reminder') {
                        $reminder = \App\Models\Reminder::find($trashItem->item_id);
                        if ($reminder) {
                            $reminder->update([
                                'completed' => false,
                                'completed_at' => null,
                            ]);
                            $trashItem->delete();
                            $restoredCount++;
                        }
                    }
                    
                    if ($trashItem->item_type === 'survey') {
                        $survey = \App\Models\Survey::where('survey_id', $trashItem->item_id)->first();
                        if ($survey) {
                            $survey->update([
                                'is_deleted' => false,
                                'deleted_at' => null,
                            ]);
                            $trashItem->delete();
                            $restoredCount++;
                        }
                    }
                    
                    if ($trashItem->item_type === 'event') {
                        $event = \App\Models\Event::withTrashed()->where('event_id', $trashItem->item_id)->first();
                        if ($event) {
                            $event->restore();
                            $trashItem->delete();
                            $restoredCount++;
                        }
                    }
                }
            });
            
            \Log::info('Successfully restored ' . $restoredCount . ' items');
            return back()->with('success', $restoredCount . '件のアイテムを復元しました。');
        } catch (\Exception $e) {
            \Log::error('TrashController restoreMultiple error: ' . $e->getMessage());
            return back()->with('error', '復元に失敗しました。');
        }
    }

    /**
     * Permanently delete an item.
     */
    public function destroy($id)
    {
        try {
            \Log::info('TrashController destroy called with ID: ' . $id . ' for user: ' . Auth::id());
            
            $trashItem = TrashItem::where('id', $id)
                ->where('user_id', Auth::id())
                ->first();
                
            if (!$trashItem) {
                \Log::error('TrashItem not found for ID: ' . $id);
                return back()->with('error', 'アイテムが見つかりません。');
            }

            \DB::transaction(function () use ($trashItem) {
                if ($trashItem->item_type === 'shared_note') {
                    // メモを完全削除
                    \App\Models\SharedNote::where('note_id', $trashItem->item_id)->delete();
                }
                
                if ($trashItem->item_type === 'reminder') {
                    // リマインダーを完全削除
                    \App\Models\Reminder::where('reminder_id', $trashItem->item_id)->delete();
                }
                
                if ($trashItem->item_type === 'survey') {
                    // アンケートを完全削除
                    $survey = \App\Models\Survey::where('survey_id', $trashItem->item_id)->first();
                    if ($survey) {
                        // 関連データを削除
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
                }
                
                if ($trashItem->item_type === 'event') {
                    // イベントを完全削除
                    \App\Models\Event::withTrashed()->where('event_id', $trashItem->item_id)->forceDelete();
                }

                // ゴミ箱からも削除
                $trashItem->delete();
            });
            
            \Log::info('Successfully permanently deleted item: ' . $trashItem->original_title);
            return back()->with('success', 'アイテムを完全に削除しました。');
        } catch (\Exception $e) {
            \Log::error('TrashController destroy error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return back()->with('error', '削除に失敗しました: ' . $e->getMessage());
        }
    }

    /**
     * Delete multiple items.
     */
    public function destroyMultiple(Request $request)
    {
        try {
            $ids = $request->input('ids', []);
            \Log::info('TrashController destroyMultiple called with IDs: ' . json_encode($ids));
            
            $trashItems = TrashItem::whereIn('id', $ids)
                ->where('user_id', Auth::id())
                ->get();
            
            if ($trashItems->isEmpty()) {
                return back()->with('error', 'アイテムが見つかりません。');
            }

            \DB::transaction(function () use ($trashItems) {
                foreach ($trashItems as $item) {
                    if ($item->item_type === 'shared_note') {
                        \App\Models\SharedNote::where('note_id', $item->item_id)->delete();
                    }
                    
                    if ($item->item_type === 'reminder') {
                        \App\Models\Reminder::where('reminder_id', $item->item_id)->delete();
                    }
                    
                    if ($item->item_type === 'survey') {
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
                    }
                    
                    if ($item->item_type === 'event') {
                        \App\Models\Event::withTrashed()->where('event_id', $item->item_id)->forceDelete();
                    }

                    $item->delete();
                }
            });
            
            \Log::info('Successfully deleted multiple items: ' . $trashItems->count());
            return back()->with('success', $trashItems->count() . '件のアイテムを削除しました。');
        } catch (\Exception $e) {
            \Log::error('TrashController destroyMultiple error: ' . $e->getMessage());
            return back()->with('error', '削除に失敗しました。');
        }
    }

    /**
     * Empty the entire trash.
     */
    public function emptyTrash()
    {
        try {
            \Log::info('TrashController emptyTrash called for user: ' . Auth::id());
            
            $trashItems = TrashItem::where('user_id', Auth::id())->get();
            \Log::info('Found ' . $trashItems->count() . ' items to permanently delete');

            \DB::transaction(function () use ($trashItems) {
                foreach ($trashItems as $item) {
                    if ($item->item_type === 'shared_note') {
                        \App\Models\SharedNote::where('note_id', $item->item_id)
                            ->delete();
                    }
                    
                    if ($item->item_type === 'reminder') {
                        \App\Models\Reminder::where('reminder_id', $item->item_id)
                            ->delete();
                    }
                    
                    if ($item->item_type === 'survey') {
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
                    }
                    
                    if ($item->item_type === 'event') {
                        \App\Models\Event::withTrashed()->where('event_id', $item->item_id)->forceDelete();
                    }
                }

                TrashItem::where('user_id', Auth::id())->delete();
            });
            
            \Log::info('Successfully emptied trash');
            return back()->with('success', 'ゴミ箱を空にしました。');
        } catch (\Exception $e) {
            \Log::error('TrashController emptyTrash error: ' . $e->getMessage());
            return back()->with('error', 'ゴミ箱を空にできませんでした。');
        }
    }
}