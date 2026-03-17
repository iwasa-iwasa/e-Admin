declare namespace App.Models {
    export type Department = {
        id: number;
        name: string;
        parent_id: number | null;
        is_active: boolean;
        created_at: string | null;
        updated_at: string | null;
        deleted_at: string | null;
        users: User[];
    };

    export type Calendar = {
        calendar_id: number;
        calendar_name: string;
        calendar_type: string;
        description: string | null;
        created_at: string | null;
        updated_at: string | null;
        deleted_at: string | null;
    };

    export type User = {
        id: number;
        name: string;
        avatar?: string;
        email: string;
        email_verified_at: string | null;
        department: string;
        department_id?: number | null;
        role: string;
        role_type?: string;
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
        deadline?: string;
        is_pinned?: boolean;
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
        deleted_at: string | null;
        version: number;
        // リレーション
        creator?: App.Models.User;
        participants?: App.Models.User[];
        recurrence?: EventRecurrence;
        attachments?: EventAttachment[];
    };

    // 展開済みイベント用を追加
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
        version: number;
        creator?: App.Models.User;
        participants?: App.Models.User[];
        attachments?: EventAttachment[];
        recurrence?: EventRecurrence;
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
