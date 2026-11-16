{{-- "File a report" on My Complaints: the shared report modal (reports/_file-modal), returning to this list.
     Opened by any button that does $dispatch('open-file-report'). Needs $reportProperties, $reportUsers,
     $reportCategories (Landlord\ReportController::index). --}}
@include('reports._file-modal', ['returnTo' => 'complaints', 'defaultTarget' => 'user', 'modalTitle' => 'File a report'])
