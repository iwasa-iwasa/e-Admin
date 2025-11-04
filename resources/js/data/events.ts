// 共有カレンダーの共通イベントデータ

export interface Event {
  id: string;
  title: string;
  color: string;
  assignee: string;
  time?: string;
  department?: string;
  location?: string;
  description?: string;
  date?: string;
  startDate?: string; // 開始日（複数日イベント用）
  endDate?: string;   // 終了日（複数日イベント用）
  isMultiDay?: boolean; // 複数日イベントかどうか
}

export function getEventsForDate(day: number, month: number): Event[] {
  const events: Event[] = [];
  if (month === 9) {
    // 10月
    
    // 複数日にわたるイベント（10/14-10/17）
    if (day >= 14 && day <= 17) {
      events.push({
        id: "multi-14-17",
        title: "経営戦略会議（4日間）",
        color: "bg-red-500",
        assignee: "田中",
        time: "09:00",
        department: "総務部",
        location: "大会議室",
        description: "Q4の経営戦略を策定する重要会議",
        date: `2025-10-${day}`,
        startDate: "2025-10-14",
        endDate: "2025-10-17",
        isMultiDay: true,
      });
    }
    
    // 複数日にわたるイベント（10/20-10/22）
    if (day >= 20 && day <= 22) {
      events.push({
        id: "multi-20-22",
        title: "新システム導入研修",
        color: "bg-purple-500",
        assignee: "全員",
        time: "10:00",
        department: "総務部",
        location: "研修室",
        description: "新勤怠管理システムの全社員向け研修",
        date: `2025-10-${day}`,
        startDate: "2025-10-20",
        endDate: "2025-10-22",
        isMultiDay: true,
      });
    }
    
    if (day === 14) {
      events.push({
        id: "14-1",
        title: "チームMTG",
        color: "bg-blue-500",
        assignee: "田中",
        time: "09:00",
        department: "総務部",
        location: "会議室A",
        description: "週次のチームミーティング。進捗確認と今週のタスク割り振り",
        date: "2025-10-14",
      });
      events.push({
        id: "14-2",
        title: "備品発注",
        color: "bg-green-500",
        assignee: "佐藤",
        time: "14:00",
        department: "総務部",
        location: "",
        description: "月次の備品発注作業",
        date: "2025-10-14",
      });
    }
    if (day === 15) {
      events.push({
        id: "15-1",
        title: "予算会議",
        color: "bg-purple-500",
        assignee: "田中",
        time: "10:00",
        department: "総務部",
        location: "大会議室",
        description: "来期予算の検討会議",
        date: "2025-10-15",
      });
      events.push({
        id: "15-2",
        title: "来客対応",
        color: "bg-yellow-500",
        assignee: "鈴木",
        time: "13:00",
        department: "総務部",
        location: "エントランス",
        description: "A社 山本様の来訪対応",
        date: "2025-10-15",
      });
      events.push({
        id: "15-3",
        title: "書類整理",
        color: "bg-pink-500",
        assignee: "山田",
        time: "15:00",
        department: "総務部",
        location: "",
        description: "月次書類の整理とファイリング",
        date: "2025-10-15",
      });
    }
    if (day === 16) {
      events.push({
        id: "16-1",
        title: "備品発注期限",
        color: "bg-orange-500",
        assignee: "佐藤",
        time: "14:00",
        department: "総務部",
        location: "",
        description: "今月の備品発注締切",
        date: "2025-10-16",
      });
      events.push({
        id: "16-2",
        title: "勤怠確認",
        color: "bg-red-500",
        assignee: "鈴木",
        time: "10:00",
        department: "総務部",
        location: "",
        description: "全社員の勤怠データ確認",
        date: "2025-10-16",
      });
    }
    if (day === 17) {
      events.push({
        id: "17-1",
        title: "月次報告準備",
        color: "bg-indigo-500",
        assignee: "田中",
        time: "09:00",
        department: "総務部",
        location: "",
        description: "月次報告資料の作成",
        date: "2025-10-17",
      });
      events.push({
        id: "17-2",
        title: "在庫確認",
        color: "bg-green-500",
        assignee: "佐藤",
        time: "11:00",
        department: "総務部",
        location: "倉庫",
        description: "備品在庫の棚卸",
        date: "2025-10-17",
      });
    }
    if (day === 18) {
      events.push({
        id: "18-1",
        title: "部署MTG",
        color: "bg-green-500",
        assignee: "全員",
        time: "15:00",
        department: "総務部",
        location: "会議室B",
        description: "部署全体の定例ミーティング",
        date: "2025-10-18",
      });
      events.push({
        id: "18-2",
        title: "新人研修",
        color: "bg-blue-500",
        assignee: "鈴木",
        time: "10:00",
        department: "総務部",
        location: "研修室",
        description: "新入社員向け総務研修",
        date: "2025-10-18",
      });
    }
    if (day === 19) {
      events.push({
        id: "19-1",
        title: "クライアント訪問",
        color: "bg-purple-500",
        assignee: "田中",
        time: "13:00",
        department: "総務部",
        location: "B社",
        description: "B社への定期訪問",
        date: "2025-10-19",
      });
    }
    if (day === 20) {
      events.push({
        id: "20-1",
        title: "給与計算",
        color: "bg-red-500",
        assignee: "鈴木",
        time: "09:30",
        department: "総務部",
        location: "",
        description: "今月の給与計算作業",
        date: "2025-10-20",
      });
      events.push({
        id: "20-2",
        title: "書類提出",
        color: "bg-yellow-600",
        assignee: "山田",
        time: "13:00",
        department: "総務部",
        location: "",
        description: "各種申請書類の提出",
        date: "2025-10-20",
      });
      events.push({
        id: "20-3",
        title: "システム説明会",
        color: "bg-orange-500",
        assignee: "佐藤",
        time: "16:00",
        department: "総務部",
        location: "大会議室",
        description: "新勤怠システムの説明会",
        date: "2025-10-20",
      });
    }
    if (day === 21) {
      events.push({
        id: "21-1",
        title: "週次レビュー",
        color: "bg-blue-500",
        assignee: "全員",
        time: "10:00",
        department: "総務部",
        location: "会議室A",
        description: "今週の振り返りと来週の計画",
        date: "2025-10-21",
      });
      events.push({
        id: "21-2",
        title: "備品棚卸",
        color: "bg-green-500",
        assignee: "佐藤",
        time: "14:00",
        department: "総務部",
        location: "倉庫",
        description: "備品の在庫確認",
        date: "2025-10-21",
      });
    }
    if (day === 22) {
      events.push({
        id: "22-1",
        title: "人事面談",
        color: "bg-pink-500",
        assignee: "田中",
        time: "11:00",
        department: "総務部",
        location: "面談室",
        description: "定期人事面談",
        date: "2025-10-22",
      });
      events.push({
        id: "22-2",
        title: "契約書確認",
        color: "bg-purple-500",
        assignee: "山田",
        time: "15:00",
        department: "総務部",
        location: "",
        description: "取引先との契約書チェック",
        date: "2025-10-22",
      });
    }
    if (day === 23) {
      events.push({
        id: "23-1",
        title: "社内研修",
        color: "bg-orange-500",
        assignee: "鈴木",
        time: "09:00",
        department: "総務部",
        location: "研修室",
        description: "コンプライアンス研修",
        date: "2025-10-23",
      });
    }
    if (day === 24) {
      events.push({
        id: "24-1",
        title: "月末処理",
        color: "bg-red-500",
        assignee: "佐藤",
        time: "10:00",
        department: "総務部",
        location: "",
        description: "月末の経理処理",
        date: "2025-10-24",
      });
      events.push({
        id: "24-2",
        title: "来客準備",
        color: "bg-yellow-500",
        assignee: "山田",
        time: "13:00",
        department: "総務部",
        location: "会議室B",
        description: "明日の来客準備",
        date: "2025-10-24",
      });
      events.push({
        id: "24-3",
        title: "午後休（病院）",
        color: "bg-teal-500",
        assignee: "鈴木",
        time: "13:00-17:00",
        department: "総務部",
        location: "",
        description: "定期検診のため午後休取得",
        date: "2025-10-24",
      });
    }
    if (day === 25) {
      events.push({
        id: "25-1",
        title: "月次報告",
        color: "bg-indigo-500",
        assignee: "田中",
        time: "11:00",
        department: "総務部",
        location: "大会議室",
        description: "今月の活動報告",
        date: "2025-10-25",
      });
      events.push({
        id: "25-2",
        title: "部署会議",
        color: "bg-blue-500",
        assignee: "全員",
        time: "14:00",
        department: "総務部",
        location: "会議室A",
        description: "月次の部署会議",
        date: "2025-10-25",
      });
      events.push({
        id: "25-3",
        title: "書類チェック",
        color: "bg-green-500",
        assignee: "鈴木",
        time: "16:00",
        department: "総務部",
        location: "",
        description: "提出書類の最終確認",
        date: "2025-10-25",
      });
    }
    if (day === 27) {
      events.push({
        id: "27-1",
        title: "有給休暇（私用）",
        color: "bg-teal-500",
        assignee: "田中",
        time: "終日",
        department: "総務部",
        location: "",
        description: "有給休暇を取得します",
        date: "2025-10-27",
      });
    }
  }
  return events;
}
