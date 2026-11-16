import { Calendar } from '@fullcalendar/core'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import listPlugin from '@fullcalendar/list'
import interactionPlugin from '@fullcalendar/interaction'
import elLocale from '@fullcalendar/core/locales/el'

// Dates travel between server and browser as local wall-clock strings ("2026-12-07T18:00:00").
// FullCalendar runs in UTC mode so the browser's own timezone never shifts them.
let toWallClock = (date) => date.toISOString().slice(0, 19)

export default (config) => ({
    calendar: null,

    init() {
        let isMobile = window.matchMedia('(max-width: 767px)').matches

        this.calendar = new Calendar(this.$refs.calendar, {
            plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
            locale: elLocale,
            timeZone: 'UTC',
            now: () => new Date(Date.now() + config.utcOffsetMinutes * 60000),
            initialView: isMobile ? 'timeGridDay' : 'timeGridWeek',
            headerToolbar: isMobile
                ? { left: 'prev,next', center: 'title', right: 'today' }
                : { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek' },
            footerToolbar: isMobile ? { center: 'timeGridDay,listWeek,dayGridMonth' } : false,
            height: 'auto',
            firstDay: 1,
            allDaySlot: false,
            nowIndicator: true,
            slotDuration: '00:30:00',
            slotMinTime: config.slotMinTime,
            slotMaxTime: config.slotMaxTime,
            businessHours: config.businessHours,
            selectable: true,
            selectMirror: true,
            editable: true,
            longPressDelay: 400,
            eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
            slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },

            events: (info, success, failure) => {
                this.$wire.events(toWallClock(info.start), toWallClock(info.end)).then(success).catch(failure)
            },

            select: (info) => {
                this.$wire.openCreate(toWallClock(info.start), toWallClock(info.end))
                this.calendar.unselect()
            },

            dateClick: (info) => {
                if (info.view.type === 'dayGridMonth') {
                    this.$wire.openCreate(toWallClock(info.date).slice(0, 10) + 'T09:00:00', null)
                }
            },

            eventClick: (info) => {
                info.jsEvent.preventDefault()
                let props = info.event.extendedProps

                if (props.kind === 'session') this.$wire.openSession(props.recordId)
                if (props.kind === 'event') this.$wire.openEvent(props.recordId)
            },

            eventDrop: (info) => this.move(info),
            eventResize: (info) => this.move(info),
        })

        this.calendar.render()
    },

    async move(info) {
        let props = info.event.extendedProps
        let moved = await this.$wire.moveEntry(props.kind, props.recordId, toWallClock(info.event.start), toWallClock(info.event.end))

        if (! moved) info.revert()
    },

    refresh() {
        this.calendar?.refetchEvents()
    },

    destroy() {
        this.calendar?.destroy()
    },
})
