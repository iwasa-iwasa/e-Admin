declare namespace App.Models {
export type Calendar = {
calendar_id: number;
calendar_name: string;
calendar_type: string;
created_at: string | null;
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
export type Event = {
event_id: number;
calendar_id: number;
title: string;
description: string | null;
location: string | null;
url: string | null;
category: string;
importance: string;
start_date: string;
start_time: string | null;
end_date: string;
end_time: string | null;
is_all_day: boolean;
created_by: number;
is_deleted: boolean;
created_at: string | null;
updated_at: string | null;
deleted_at: string | null;
rrule?: string;
duration?: string;
recurrence: EventRecurrence | null;
attachments: EventAttachment[];
participants: User[];
};
export type EventRecurrence = {
recurrence_id: number;
event_id: number;
recurrence_type: string;
recurrence_interval: number;
by_day: Array<any> | null;
by_set_pos: number | null;
recurrence_unit: string | null;
end_date: string | null;
};
export type Example = {
id: number;
name: string;
is_active: boolean;
};
export type NoteTag = {
tag_id: number;
tag_name: string;
};
export type Reminder = {
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
export type SharedNote = {
note_id: number;
title: string;
content: string | null;
author_id: number;
color: string;
priority: string;
deadline: string | null;
is_deleted: boolean;
created_at: string | null;
updated_at: string | null;
deleted_at: string | null;
tags: NoteTag[];
author: User;
is_pinned: boolean;
};
export type SurveyAnswer = {
answer_id: number;
response_id: number;
question_id: number;
answer_text: string | null;
selected_option_id: number | null;
};
export type Survey = {
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
creator: User;
questions: SurveyQuestion[];
responses: SurveyResponse[];
};
export type SurveyQuestion = {
question_id: number;
survey_id: number;
question_text: string;
question_type: string;
is_required: boolean;
display_order: number;
};
export type SurveyQuestionOption = {
option_id: number;
question_id: number;
option_text: string;
display_order: number;
};
export type SurveyResponse = {
response_id: number;
survey_id: number;
respondent_id: number;
submitted_at: string | null;
answers: SurveyAnswer[];
respondent: User;
};
export type TrashItem = {
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
avatar: string | null;
};
}
