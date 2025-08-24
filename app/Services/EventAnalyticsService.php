<?php

namespace App\Services;

use App\Models\Event;
use App\Models\MemberCheckIn;
use App\Modules\Membership\Models\MembershipLevel;
use Illuminate\Support\Facades\DB;

class EventAnalyticsService
{
    /**
     * Get paginated check-ins for a specific event
     */
    public function getEventCheckIns(int $eventId, array $filters = [])
    {
        $query = MemberCheckIn::forEvent($eventId)
            ->with(['member', 'scanner', 'eventOccurrence']);
        
        if (isset($filters['occurrence_id'])) {
            $query->where('event_occurrence_id', $filters['occurrence_id']);
        }
        
        if (isset($filters['date_from'])) {
            $query->where('scanned_at', '>=', $filters['date_from']);
        }
        
        if (isset($filters['date_to'])) {
            $query->where('scanned_at', '<=', $filters['date_to']);
        }
        
        return $query->orderBy('scanned_at', 'desc')->paginate(50);
    }
    
    /**
     * Get comprehensive attendance statistics for an event
     */
    public function getEventAttendanceStats(int $eventId): array
    {
        $event = Event::with('eventOccurrences')->findOrFail($eventId);
        
        return [
            'total_checkins' => MemberCheckIn::forEvent($eventId)->count(),
            'unique_members' => MemberCheckIn::forEvent($eventId)
                ->distinct('user_id')
                ->count('user_id'),
            'by_occurrence' => $this->getCheckInsByOccurrence($eventId),
            'by_membership_level' => $this->getCheckInsByMembershipLevel($eventId),
            'recent_checkins' => $this->getRecentCheckIns($eventId),
            'checkins_over_time' => $this->getCheckInsOverTime($eventId),
        ];
    }
    
    /**
     * Get check-ins grouped by event occurrence
     */
    private function getCheckInsByOccurrence(int $eventId)
    {
        return MemberCheckIn::forEvent($eventId)
            ->select('event_occurrence_id', DB::raw('COUNT(*) as count'))
            ->with('eventOccurrence:id,name,start_at_utc')
            ->groupBy('event_occurrence_id')
            ->get();
    }
    
    /**
     * Get check-ins grouped by membership level
     */
    private function getCheckInsByMembershipLevel(int $eventId)
    {
        return DB::table('member_check_ins')
            ->join('user_memberships', 'member_check_ins.user_id', '=', 'user_memberships.user_id')
            ->join('membership_levels', 'user_memberships.membership_level_id', '=', 'membership_levels.id')
            ->where('member_check_ins.event_id', $eventId)
            ->where('user_memberships.status', 'active')
            ->select(
                'membership_levels.id as level_id',
                'membership_levels.name as level_name',
                DB::raw('COUNT(DISTINCT member_check_ins.user_id) as unique_members'),
                DB::raw('COUNT(member_check_ins.id) as total_checkins')
            )
            ->groupBy('membership_levels.id', 'membership_levels.name')
            ->get()
            ->map(function ($item) {
                // Parse JSON name field
                $names = json_decode($item->level_name, true);
                $item->level_display_name = $names[app()->getLocale()] ?? $names['en'] ?? 'Unknown Level';
                return $item;
            });
    }
    
    /**
     * Get the most recent check-ins for an event
     */
    private function getRecentCheckIns(int $eventId, int $limit = 10)
    {
        return MemberCheckIn::forEvent($eventId)
            ->with(['member:id,name,email', 'eventOccurrence:id,name'])
            ->orderBy('scanned_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($checkIn) {
                return [
                    'id' => $checkIn->id,
                    'member_name' => $checkIn->member->name,
                    'member_email' => $checkIn->member->email,
                    'occurrence_name' => $checkIn->eventOccurrence?->name ?? 'Main Event',
                    'scanned_at' => $checkIn->scanned_at,
                    'location' => $checkIn->location,
                ];
            });
    }
    
    /**
     * Get check-ins over time for charting
     */
    private function getCheckInsOverTime(int $eventId)
    {
        return MemberCheckIn::forEvent($eventId)
            ->select(
                DB::raw('DATE(scanned_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy(DB::raw('DATE(scanned_at)'))
            ->orderBy('date')
            ->get();
    }
    
    /**
     * Get member engagement statistics across multiple events
     */
    public function getMemberEngagementStats(int $userId): array
    {
        $checkIns = MemberCheckIn::where('user_id', $userId)
            ->with(['event:id,name', 'eventOccurrence:id,name'])
            ->orderBy('scanned_at', 'desc')
            ->get();
            
        $uniqueEvents = $checkIns->unique('event_id');
        
        return [
            'total_checkins' => $checkIns->count(),
            'unique_events_attended' => $uniqueEvents->count(),
            'most_recent_checkin' => $checkIns->first()?->scanned_at,
            'events_attended' => $uniqueEvents->map(function ($checkIn) {
                return [
                    'event_id' => $checkIn->event_id,
                    'event_name' => $checkIn->event->name,
                    'last_attended' => $checkIn->scanned_at,
                ];
            })->values(),
        ];
    }
    
    /**
     * Get event popularity rankings
     */
    public function getEventPopularityRankings(array $eventIds = null, int $limit = 10): array
    {
        $query = DB::table('member_check_ins')
            ->join('events', 'member_check_ins.event_id', '=', 'events.id')
            ->select(
                'events.id',
                'events.name',
                DB::raw('COUNT(DISTINCT member_check_ins.user_id) as unique_attendees'),
                DB::raw('COUNT(member_check_ins.id) as total_checkins')
            )
            ->groupBy('events.id', 'events.name');
            
        if ($eventIds) {
            $query->whereIn('events.id', $eventIds);
        }
        
        return $query->orderBy('unique_attendees', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                // Parse JSON name field
                $names = json_decode($item->name, true);
                $item->display_name = $names[app()->getLocale()] ?? $names['en'] ?? 'Unknown Event';
                return $item;
            });
    }
}