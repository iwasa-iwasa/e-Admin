@echo off
echo VueDatePickerを日本仕様に統一します...

cd /d "%~dp0resources\js"

REM GlobalSearch.vue
powershell -Command "(Get-Content 'components\GlobalSearch.vue') -replace ':locale=\"\"ja\"\"', 'locale=\"\"ja\"\"' -replace 'format=\"\"yyyy-MM-dd\"\"', 'format=\"\"yyyy/MM/dd\"\"' | Set-Content 'components\GlobalSearch.vue'"

REM DateInput.vue
powershell -Command "(Get-Content 'features\survey\components\inputs\DateInput.vue') -replace ':locale=\"\"ja\"\"', 'locale=\"\"ja\"\"' -replace 'format=\"\"yyyy-MM-dd HH:mm\"\"', 'format=\"\"yyyy/MM/dd HH:mm\"\"' | Set-Content 'features\survey\components\inputs\DateInput.vue'"

REM CreateNoteDialog.vue
powershell -Command "(Get-Content 'components\CreateNoteDialog.vue') -replace ':locale=\"\"ja\"\"', 'locale=\"\"ja\"\"' | Set-Content 'components\CreateNoteDialog.vue'"

REM NoteDetailDialog.vue
powershell -Command "(Get-Content 'components\NoteDetailDialog.vue') -replace ':locale=\"\"ja\"\"', 'locale=\"\"ja\"\"' | Set-Content 'components\NoteDetailDialog.vue'"

REM ReminderDetailDialog.vue
powershell -Command "(Get-Content 'components\ReminderDetailDialog.vue') -replace ':locale=\"\"ja\"\"', 'locale=\"\"ja\"\"' | Set-Content 'components\ReminderDetailDialog.vue'"

REM SurveyForm.vue
powershell -Command "(Get-Content 'features\survey\components\SurveyEditor\SurveyForm.vue') -replace ':locale=\"\"ja\"\"', 'locale=\"\"ja\"\"' | Set-Content 'features\survey\components\SurveyEditor\SurveyForm.vue'"

REM Notes.vue
powershell -Command "(Get-Content 'Pages\Notes.vue') -replace ':locale=\"\"ja\"\"', 'locale=\"\"ja\"\"' | Set-Content 'Pages\Notes.vue'"

REM Trash.vue
powershell -Command "(Get-Content 'Pages\Trash.vue') -replace ':locale=\"\"ja\"\"', 'locale=\"\"ja\"\"' | Set-Content 'Pages\Trash.vue'"

echo 完了しました！
pause
