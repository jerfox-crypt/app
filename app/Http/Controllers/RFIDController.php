<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RFIDController extends Controller
{
    public function handleScan(Request $request)
    {
        // Validate the request
        $validated = $request->validate([
            'rfid' => 'required|string|max:50',
            'location' => 'nullable|string|max:100'
        ]);
        
        $rfid = trim($validated['rfid']);
        $location = $validated['location'] ?? 'default';
        
        Log::info('RFID Scan Attempt', [
            'rfid' => $rfid,
            'location' => $location,
            'ip' => $request->ip()
        ]);
        
        // Rate limiting check (optional)
        if ($this->isRateLimited($rfid)) {
            return response()->json([
                'success' => false,
                'error' => 'RATE_LIMITED',
                'message' => 'Please wait before scanning again'
            ], 429);
        }
        
        // Check if RFID exists in studinfo
        $student = DB::table('studinfo')->where('rfid', $rfid)->first();
        
        if (!$student) {
            // Log failed scan
            $this->logScan($rfid, null, 'not_found', 'RFID not found in database', $location);
            
            return response()->json([
                'success' => false,
                'error' => 'RFID_NOT_FOUND',
                'message' => 'RFID card not registered in the system'
            ], 404);
        }
        
        // Format student data for response
        $studentData = $this->formatStudentData($student);
        
        // Log successful scan
        $this->logScan($rfid, $student->id, 'success', 'Scan successful', $location, $studentData);
        
        // Return success response
        return response()->json([
            'success' => true,
            'message' => $this->getWelcomeMessage($student),
            'data' => $studentData,
            'scan_time' => now()->format('Y-m-d H:i:s')
        ]);
    }
    
    private function formatStudentData($student)
    {
        // Build full name
        $fullName = $student->lastname . ', ' . $student->firstname;
        if ($student->middlename) {
            $fullName .= ' ' . substr($student->middlename, 0, 1) . '.';
        }
        if ($student->suffix) {
            $fullName .= ' ' . $student->suffix;
        }
        
        // Determine primary contact
        $primaryContact = null;
        if ($student->ismothernum == 1 && !empty($student->mcontactno)) {
            $primaryContact = $student->mcontactno;
        } elseif ($student->isfathernum == 1 && !empty($student->fcontactno)) {
            $primaryContact = $student->fcontactno;
        } elseif ($student->isguardannum == 1 && !empty($student->gcontactno)) {
            $primaryContact = $student->gcontactno;
        }
        
        return [
            'id' => $student->id,
            'sid' => $student->sid,
            'lrn' => $student->lrn,
            'fullname' => $fullName,
            'lastname' => $student->lastname,
            'firstname' => $student->firstname,
            'middlename' => $student->middlename,
            'suffix' => $student->suffix,
            'levelname' => $student->levelname,
            'sectionname' => $student->sectionname,
            'level_section' => $student->levelname . ' - ' . $student->sectionname,
            'gender' => $student->gender,
            'photo_url' => $student->picurl,
            'primary_contact' => $primaryContact,
            'mcontactno' => $student->mcontactno,
            'fcontactno' => $student->fcontactno,
            'gcontactno' => $student->gcontactno,
            'ismothernum' => $student->ismothernum,
            'isfathernum' => $student->isfathernum,
            'isguardannum' => $student->isguardannum
        ];
    }
    
    private function getWelcomeMessage($student)
    {
        $greeting = 'Good ' . $this->getTimeOfDayGreeting();
        $name = $student->firstname;
        
        return "{$greeting}, {$name}! Welcome to " . config('app.name', 'the school');
    }
    
    private function getTimeOfDayGreeting()
    {
        $hour = now()->hour;
        
        if ($hour < 12) {
            return 'morning';
        } elseif ($hour < 18) {
            return 'afternoon';
        } else {
            return 'evening';
        }
    }
    
    private function isDuplicateScan($studentId, $location)
    {
        // Check if same student scanned at same location within last 5 minutes
        $fiveMinutesAgo = Carbon::now()->subMinutes(5);
        
        return DB::table('scan_logs')
            ->where('student_id', $studentId)
            ->where('location', $location)
            ->where('status', 'success')
            ->where('scan_time', '>', $fiveMinutesAgo)
            ->exists();
    }
    
    private function isRateLimited($rfid)
    {
        // Allow max 10 scans per minute per RFID
        $oneMinuteAgo = Carbon::now()->subMinute();
        
        $scanCount = DB::table('scan_logs')
            ->where('rfid', $rfid)
            ->where('scan_time', '>', $oneMinuteAgo)
            ->count();
            
        return $scanCount >= 10;
    }
    
    private function logScan($rfid, $studentId, $status, $message, $location, $metadata = null)
    {
        DB::table('scan_logs')->insert([
            'rfid' => $rfid,
            'student_id' => $studentId,
            'sid' => $studentId ? DB::table('studinfo')->where('id', $studentId)->value('sid') : null,
            'lrn' => $studentId ? DB::table('studinfo')->where('id', $studentId)->value('lrn') : null,
            'scan_type' => 'attendance',
            'location' => $location,
            'status' => $status,
            'message' => $message,
            'metadata' => $metadata ? json_encode($metadata) : null,
            'scan_time' => now()
        ]);
    }
    
    // Optional: Get recent scans for dashboard
    public function getRecentScans()
    {
        $scans = DB::table('scan_logs')
            ->where('status', 'success')
            ->orderBy('scan_time', 'desc')
            ->limit(20)
            ->get();
            
        return response()->json([
            'success' => true,
            'data' => $scans
        ]);
    }
}