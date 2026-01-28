declare namespace App.Data {
  export type CalendarData = {
    calendar_id: number;
    calendar_name: string;
    calendar_type: string;
    created_at: string | null;
  };

  export type EventAttachmentData = {
    attachment_id: number;
    event_id: number;
    file_name: string;
    file_path: string;
    file_size: number | null;
    mime_type: string | null;
    uploaded_at: string | null;
  };

  export type EventData = {
    event_id: number;
    calendar_id: number;

    title: string;
    description: string | null;
    location: string | null;
    url: string | null;

    category: App.Enums.EventCategory;
    importance: App.Enums.EventImportance;

    start_date: string;
    start_time: string | null;
    end_date: string;
    end_time: string | null;
    is_all_day: boolean;

    created_by: number;
    progress?: number;

    is_deleted: boolean;
    created_at: string | null;
    updated_at: string | null;
    deleted_at: string | null;
  };

  export type EventRecurrenceData = {
    recurrence_id: number;
    event_id: number;
    recurrence_type: string;
    recurrence_interval: number;
    by_day: Array<any> | null;
    by_set_pos: number | null;
    recurrence_unit: string | null;
    end_date: string | null;
  };

  export type ExampleData = {
    id: number;
    name: string;
    is_active: boolean;
  };

  export type NoteTagData = {
    tag_id: number;
    tag_name: string;
  };

  export type ReminderData = {
    reminder_id: number;
    user_id: number;
    title: string;
    description: string | null;
    deadline: string;
    category: string;
    completed: boolean;
    completed_at: string | null;
    is_deleted: boolean;
    created_at: string | null;
    updated_at: string | null;
    deleted_at: string | null;
  };

  export type SharedNoteData = {
    note_id: number;
    title: string;
    content: string | null;
    author_id: number;
    color: string;
    priority: string;
    deadline_date: string | null;
    deadline_time: string | null;
    is_deleted: boolean;
    created_at: string | null;
    updated_at: string | null;
    deleted_at: string | null;
  };

  export type SurveyAnswerData = {
    answer_id: number;
    response_id: number;
    question_id: number;
    answer_text: string | null;
    selected_option_id: number | null;
  };

  export type SurveyData = {
    survey_id: number;
    title: string;
    description: string | null;
    created_by: number;
    deadline: string | null;
    is_active: boolean;
    is_deleted: boolean;
    created_at: string | null;
    updated_at: string | null;
    deleted_at: string | null;
  };

  export type SurveyQuestionData = {
    question_id: number;
    survey_id: number;
    question_text: string;
    question_type: string;
    is_required: boolean;
    display_order: number;
  };

  export type SurveyQuestionOptionData = {
    option_id: number;
    question_id: number;
    option_text: string;
    display_order: number;
  };

  export type SurveyResponseData = {
    response_id: number;
    survey_id: number;
    respondent_id: number;
    submitted_at: string | null;
  };

  export type TrashItemData = {
    id: number;
    user_id: number;
    item_type: string;
    item_id: number;
    original_title: string;
    deleted_at: string | null;
    permanent_delete_at: string | null;
    created_at: string | null;
    updated_at: string | null;
  };

  export type UserData = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    department: string;
    role: string;
    is_active: boolean;
    created_at: string | null;
    updated_at: string | null;
    reason: string | null;
    deactivated_at: string | null;
  };
}

declare namespace App.Enums {
  export type EventCategory = '会議' | '業務' | '来客' | '出張' | '休暇' | 'その他';
  export type EventColor = 'blue' | 'green' | 'yellow' | 'purple' | 'pink' | 'gray';
  export type EventImportance = '重要' | '中' | '低';
}

declare namespace App.Models {
  export type User = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    department: string;
    role: string;
    is_active: boolean;
    created_at: string | null;
    updated_at: string | null;
    reason: string | null;
    deactivated_at: string | null;
  };

  export type SharedNote = {
    note_id: number;
    title: string;
    content: string | null;
    author_id: number;
    color: string;
    priority: string;
    deadline_date: string | null;
    deadline_time: string | null;
    is_deleted: boolean;
    created_at: string | null;
    updated_at: string | null;
    deleted_at: string | null;
    author?: User;
    participants?: User[];
    tags: { tag_id: number; tag_name: string }[];
  };

  export type Event = {
    event_id: number;
    calendar_id: number;

    title: string;
    description: string | null;
    location: string | null;
    url: string | null;

    category: App.Enums.EventCategory;
    importance: App.Enums.EventImportance;

    start_date: string;
    start_time: string | null;
    end_date: string;
    end_time: string | null;
    is_all_day: boolean;

    created_by: number;
    progress?: number;

    is_deleted: boolean;
    created_at: string | null;
    updated_at: string | null;
    deleted_at: string | null;
    // リレーション
    creator?: App.Models.User;
    participants?: App.Models.User[];
    recurrence?: EventRecurrence;
    attachments?: EventAttachment[];
  };

  // 展開済みイベント型を追加
  export type ExpandedEvent = {
    id: number;
    event_id: number;
    originalEventId?: number | null;
    isRecurring: boolean;
    title: string;
    description: string | null;
    location: string | null;
    url: string | null;
    category: App.Enums.EventCategory;
    importance: App.Enums.EventImportance;
    start_date: string;
    start_time: string | null;
    end_date: string;
    end_time: string | null;
    is_all_day: boolean;
    created_by: number;
    progress?: number;
    creator?: App.Models.User;
    participants?: App.Models.User[];
    attachments?: EventAttachment[];
  };

  export type EventRecurrence = {
    recurrence_id: number;
    event_id: number;
    recurrence_type: string;
    recurrence_interval: number;
    by_day: string[] | null;
    by_set_pos: number | null;
    end_date: string | null;
  };

  export type EventAttachment = {
    attachment_id: number;
    event_id: number;
    file_name: string;
    file_path: string;
    file_size: number | null;
    mime_type: string | null;
    uploaded_at: string | null;
  };
}