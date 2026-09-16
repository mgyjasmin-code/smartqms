from pathlib import Path

OUT = Path(r"C:\xampp\htdocs\smartqms\docs\diagrams")
OUT.mkdir(parents=True, exist_ok=True)

STYLE = """
<defs>
  <style>
    .bg{fill:#f8fafc}.title{font:700 38px Arial,sans-serif;fill:#0f172a}.sub{font:400 19px Arial,sans-serif;fill:#475569}
    .boundary{fill:#eff6ff;stroke:#93c5fd;stroke-width:3}.boundarylabel{font:700 22px Arial,sans-serif;fill:#1d4ed8;letter-spacing:1px}
    .entity{fill:#fff;stroke:#64748b;stroke-width:3}.process{fill:#e0f2fe;stroke:#0284c7;stroke-width:3}.service{fill:#f0fdf4;stroke:#16a34a;stroke-width:3}.store{fill:#eef2ff;stroke:#4f46e5;stroke-width:3}
    .erd{fill:#fff;stroke:#0284c7;stroke-width:3}.erdhead{fill:#e0f2fe}.uc{fill:#fff;stroke:#0284c7;stroke-width:3}
    .label{font:700 26px Arial,sans-serif;fill:#0f172a}.small{font:400 21px Arial,sans-serif;fill:#334155}.tiny{font:400 18px Arial,sans-serif;fill:#334155}
    .flow{fill:none;stroke:#475569;stroke-width:3;marker-end:url(#arrow)}.line{fill:none;stroke:#475569;stroke-width:3}.flowlabel{font:400 18px Arial,sans-serif;fill:#334155;paint-order:stroke;stroke:#f8fafc;stroke-width:7px;stroke-linejoin:round}
    .relation{font:700 18px Arial,sans-serif;fill:#475569;paint-order:stroke;stroke:#f8fafc;stroke-width:7px}.accent{fill:#0284c7}
  </style>
  <marker id="arrow" markerWidth="12" markerHeight="12" refX="10" refY="6" orient="auto" markerUnits="strokeWidth"><path d="M0 0L11 6L0 12z" fill="#475569"/></marker>
</defs>
"""

def svg(width, height, title, subtitle, body, desc):
    return f'''<svg xmlns="http://www.w3.org/2000/svg" width="{width}" height="{height}" viewBox="0 0 {width} {height}" role="img" aria-labelledby="title desc">
<title id="title">{title}</title><desc id="desc">{desc}</desc>{STYLE}
<rect class="bg" width="{width}" height="{height}"/><text class="title" x="70" y="62">{title}</text><text class="sub" x="70" y="94">{subtitle}</text>{body}</svg>'''

dfd_body = '''
<rect class="entity" x="60" y="150" width="265" height="108" rx="14"/><text class="label" x="132" y="197">Client</text><text class="small" x="132" y="227">Web / mobile user</text>
<rect class="entity" x="60" y="395" width="265" height="108" rx="14"/><text class="label" x="120" y="442">Service Staff</text><text class="small" x="120" y="472">Window operator</text>
<rect class="entity" x="60" y="640" width="265" height="108" rx="14"/><text class="label" x="105" y="687">Administrator</text><text class="small" x="105" y="717">System manager</text>
<rect class="service" x="1275" y="150" width="265" height="108" rx="14"/><text class="label" x="1320" y="197">ML Service</text><text class="small" x="1308" y="227">Wait-time prediction</text>
<rect class="entity" x="1275" y="395" width="265" height="108" rx="14"/><text class="label" x="1300" y="442">Public Display</text><text class="small" x="1320" y="472">Live queue board</text>
<rect class="service" x="1275" y="640" width="265" height="108" rx="14"/><text class="label" x="1298" y="687">Notification Service</text><text class="small" x="1300" y="717">SMS, email and alerts</text>
<rect class="boundary" x="380" y="120" width="840" height="660" rx="22"/><text class="boundarylabel" x="420" y="158">SMART QMS WEB APPLICATION</text>
<rect class="process" x="445" y="175" width="360" height="120" rx="16"/><text class="label" x="495" y="223">1. Client &amp; Ticket</text><text class="label" x="545" y="254">Management</text>
<rect class="process" x="445" y="410" width="360" height="120" rx="16"/><text class="label" x="495" y="458">2. Queue &amp; Window</text><text class="label" x="545" y="489">Operations</text>
<rect class="process" x="445" y="645" width="360" height="92" rx="16"/><text class="label" x="472" y="685">3. Administration,</text><text class="label" x="495" y="716">Analytics &amp; Reports</text>
<path class="store" d="M885 390c0-19 70-34 155-34s155 15 155 34v292c0 19-70 34-155 34s-155-15-155-34z"/><ellipse cx="1040" cy="390" rx="155" ry="34" fill="#eef2ff" stroke="#4f46e5" stroke-width="3"/><path d="M885 390v292c0 19 70 34 155 34s155-15 155-34V390" fill="none" stroke="#4f46e5" stroke-width="3"/>
<text class="label" x="947" y="518">D1 Operational Data</text><text class="small" x="925" y="550">Users, services, tickets, windows,</text><text class="small" x="937" y="578">logs, notifications and feedback</text>
<path class="flow" d="M325 204H445"/><text class="flowlabel" x="337" y="184">queue request</text><path class="flow" d="M445 255H325"/><text class="flowlabel" x="334" y="284">ticket and status</text>
<path class="flow" d="M325 449H445"/><text class="flowlabel" x="333" y="429">staff queue actions</text><path class="flow" d="M445 500H325"/><text class="flowlabel" x="335" y="530">next ticket</text>
<path class="flow" d="M325 694H445"/><text class="flowlabel" x="335" y="674">configuration</text><path class="flow" d="M445 730H325"/><text class="flowlabel" x="333" y="760">reports and metrics</text>
<path class="flow" d="M625 295V410"/><text class="flowlabel" x="645" y="365">validated ticket</text>
<path class="flow" d="M805 215H1275"/><text class="flowlabel" x="975" y="195">prediction features</text><path class="flow" d="M1275 252H805"/><text class="flowlabel" x="982" y="280">wait estimate</text>
<path class="flow" d="M805 449H885"/><text class="flowlabel" x="813" y="430">ticket data</text><path class="flow" d="M885 500H805"/><text class="flowlabel" x="813" y="529">queue state</text>
<path class="flow" d="M805 695H885"/><text class="flowlabel" x="815" y="674">report data</text><path class="flow" d="M885 730H805"/><text class="flowlabel" x="815" y="760">metrics</text>
<path class="flow" d="M805 470H1275"/><text class="flowlabel" x="990" y="450">live queue status</text><path class="flow" d="M805 694H1275"/><text class="flowlabel" x="970" y="674">alerts and feedback events</text>
'''

context_body = '''
<rect class="process" x="530" y="255" width="540" height="340" rx="24"/><text class="label" x="690" y="372">SMART QMS</text><text class="label" x="615" y="410">Queue Management System</text><text class="small" x="660" y="457">Web application and operational data</text><text class="small" x="660" y="485">for tickets, queues, services and reports</text>
<rect class="entity" x="75" y="180" width="270" height="108" rx="14"/><text class="label" x="155" y="227">Client</text><text class="small" x="130" y="257">Web / mobile user</text>
<rect class="entity" x="75" y="560" width="270" height="108" rx="14"/><text class="label" x="115" y="607">Administrator</text><text class="small" x="133" y="637">System manager</text>
<rect class="entity" x="1255" y="180" width="270" height="108" rx="14"/><text class="label" x="1315" y="227">Service Staff</text><text class="small" x="1325" y="257">Window operator</text>
<rect class="entity" x="1255" y="405" width="270" height="108" rx="14"/><text class="label" x="1300" y="452">Public Display</text><text class="small" x="1320" y="482">Live queue board</text>
<rect class="service" x="1255" y="630" width="270" height="108" rx="14"/><text class="label" x="1320" y="677">External Services</text><text class="small" x="1305" y="707">ML prediction and notifications</text>
<path class="flow" d="M345 220H530"/><text class="flowlabel" x="358" y="198">registration, booking,</text><text class="flowlabel" x="378" y="221">queue request</text><path class="flow" d="M530 275H345"/><text class="flowlabel" x="362" y="304">ticket, QR, status, alerts</text>
<path class="flow" d="M345 610H530"/><text class="flowlabel" x="361" y="590">configuration and</text><text class="flowlabel" x="377" y="613">report request</text><path class="flow" d="M530 570H345"/><text class="flowlabel" x="365" y="600">dashboards and reports</text>
<path class="flow" d="M1070 220H1255"/><text class="flowlabel" x="1095" y="198">check-in and</text><text class="flowlabel" x="1108" y="221">queue actions</text><path class="flow" d="M1255 275H1070"/><text class="flowlabel" x="1087" y="304">next ticket and window state</text>
<path class="flow" d="M1070 460H1255"/><text class="flowlabel" x="1095" y="440">live queue and</text><text class="flowlabel" x="1105" y="463">window status</text>
<path class="flow" d="M1070 548H1255V680"/><text class="flowlabel" x="1085" y="615">features, alerts and delivery status</text><path class="flow" d="M1255 715H1070"/><text class="flowlabel" x="1110" y="744">prediction result</text>
'''

erd_body = '''
<rect class="erd" x="65" y="155" width="300" height="180" rx="12"/><rect class="erdhead" x="65" y="155" width="300" height="55" rx="12"/><text class="label" x="145" y="192">USER</text><text class="small" x="90" y="240">PK  user_id</text><text class="small" x="90" y="270">role, name, contact</text><text class="small" x="90" y="300">client_type, active status</text>
<rect class="erd" x="65" y="455" width="300" height="160" rx="12"/><rect class="erdhead" x="65" y="455" width="300" height="55" rx="12"/><text class="label" x="135" y="492">STAFF</text><text class="small" x="90" y="540">PK  staff_id</text><text class="small" x="90" y="570">FK  user_id</text><text class="small" x="90" y="600">department, shift</text>
<rect class="erd" x="490" y="155" width="330" height="180" rx="12"/><rect class="erdhead" x="490" y="155" width="330" height="55" rx="12"/><text class="label" x="535" y="192">HEALTH_SERVICE</text><text class="small" x="515" y="240">PK  service_id</text><text class="small" x="515" y="270">name, queue_mode</text><text class="small" x="515" y="300">duration, active status</text>
<rect class="erd" x="1010" y="155" width="330" height="180" rx="12"/><rect class="erdhead" x="1010" y="155" width="330" height="55" rx="12"/><text class="label" x="1055" y="192">SERVICE_WINDOW</text><text class="small" x="1035" y="240">PK  window_id</text><text class="small" x="1035" y="270">FK  staff_id, service_id</text><text class="small" x="1035" y="300">name, status, active</text>
<rect class="erd" x="1010" y="455" width="330" height="135" rx="12"/><rect class="erdhead" x="1010" y="455" width="330" height="55" rx="12"/><text class="label" x="1050" y="492">COUNTER_SERVICE</text><text class="small" x="1035" y="540">PK/FK  counter_id</text><text class="small" x="1035" y="570">PK/FK  service_id</text>
<rect class="erd" x="490" y="430" width="360" height="230" rx="12"/><rect class="erdhead" x="490" y="430" width="360" height="55" rx="12"/><text class="label" x="550" y="467">QUEUE_TICKET</text><text class="small" x="515" y="518">PK  ticket_id</text><text class="small" x="515" y="548">FK  user_id, service_id, window_id</text><text class="small" x="515" y="578">reference, QR, ticket number</text><text class="small" x="515" y="608">status and lifecycle timestamps</text><text class="small" x="515" y="638">check-in method and mode</text>
<rect class="erd" x="85" y="790" width="335" height="160" rx="12"/><rect class="erdhead" x="85" y="790" width="335" height="55" rx="12"/><text class="label" x="125" y="827">WAIT_TIME_LOG</text><text class="small" x="110" y="875">PK  log_id   |   FK  ticket_id</text><text class="small" x="110" y="905">features, prediction, actual wait</text><text class="small" x="110" y="935">confidence and model version</text>
<rect class="erd" x="610" y="790" width="335" height="160" rx="12"/><rect class="erdhead" x="610" y="790" width="335" height="55" rx="12"/><text class="label" x="668" y="827">NOTIFICATION</text><text class="small" x="635" y="875">PK  notif_id   |   FK  ticket_id</text><text class="small" x="635" y="905">FK  user_id, channel, status</text><text class="small" x="635" y="935">message and sent time</text>
<rect class="erd" x="1135" y="790" width="335" height="160" rx="12"/><rect class="erdhead" x="1135" y="790" width="335" height="55" rx="12"/><text class="label" x="1222" y="827">FEEDBACK</text><text class="small" x="1160" y="875">PK  feedback_id | FK  ticket_id</text><text class="small" x="1160" y="905">FK  user_id, window_id, service_id</text><text class="small" x="1160" y="935">rating, comment, submitted time</text>
<path class="line" d="M215 335V455"/><text class="relation" x="225" y="390">1 to 0..1</text><path class="line" d="M365 245H490"/><text class="relation" x="390" y="235">1 to many</text><path class="line" d="M365 250L490 510"/><text class="relation" x="375" y="385">1 to many</text><path class="line" d="M820 245H1010"/><text class="relation" x="855" y="235">1 to many</text><path class="line" d="M820 280L1010 495"/><text class="relation" x="875" y="390">1 to many</text><path class="line" d="M365 535H490"/><text class="relation" x="385" y="525">1 to many</text><path class="line" d="M850 545H1010"/><text class="relation" x="870" y="535">many to many</text><path class="line" d="M670 660V790"/><text class="relation" x="680" y="730">1 to 1</text><path class="line" d="M580 660L280 790"/><text class="relation" x="370" y="740">1 to many</text><path class="line" d="M780 660L1260 790"/><text class="relation" x="1030" y="740">1 to 0..1</text>
'''

usecase_body = '''
<rect class="boundary" x="360" y="125" width="850" height="730" rx="22"/><text class="boundarylabel" x="400" y="163">SMART QMS</text>
<rect class="entity" x="60" y="190" width="220" height="100" rx="14"/><text class="label" x="117" y="235">Client</text><text class="small" x="95" y="264">Web / mobile user</text>
<rect class="entity" x="60" y="455" width="220" height="100" rx="14"/><text class="label" x="91" y="500">Service Staff</text><text class="small" x="92" y="529">Window operator</text>
<rect class="entity" x="60" y="715" width="220" height="100" rx="14"/><text class="label" x="90" y="760">Administrator</text><text class="small" x="100" y="789">System manager</text>
<ellipse class="uc" cx="620" cy="220" rx="175" ry="47"/><text class="label" x="515" y="229">Register / Sign In</text>
<ellipse class="uc" cx="620" cy="330" rx="175" ry="47"/><text class="label" x="495" y="339">Join or Schedule Queue</text>
<ellipse class="uc" cx="620" cy="440" rx="175" ry="47"/><text class="label" x="485" y="449">Track Ticket &amp; Wait Time</text>
<ellipse class="uc" cx="620" cy="550" rx="175" ry="47"/><text class="label" x="500" y="559">Receive Alerts / Feedback</text>
<ellipse class="uc" cx="965" cy="330" rx="175" ry="47"/><text class="label" x="858" y="339">Check In / Call Next</text>
<ellipse class="uc" cx="965" cy="440" rx="175" ry="47"/><text class="label" x="850" y="449">Serve / Complete Ticket</text>
<ellipse class="uc" cx="965" cy="550" rx="175" ry="47"/><text class="label" x="851" y="559">Manage Window Status</text>
<ellipse class="uc" cx="620" cy="705" rx="175" ry="47"/><text class="label" x="494" y="714">Manage Users &amp; Staff</text>
<ellipse class="uc" cx="965" cy="705" rx="175" ry="47"/><text class="label" x="842" y="714">Manage Services &amp; Windows</text>
<ellipse class="uc" cx="790" cy="800" rx="175" ry="47"/><text class="label" x="672" y="809">View Analytics &amp; Reports</text>
<path class="line" d="M280 240H445"/><path class="line" d="M280 245L445 330"/><path class="line" d="M280 250L445 440"/><path class="line" d="M280 255L445 550"/>
<path class="line" d="M280 505L790 330"/><path class="line" d="M280 505L790 440"/><path class="line" d="M280 505L790 550"/>
<path class="line" d="M280 765L445 705"/><path class="line" d="M280 765L790 705"/><path class="line" d="M280 765L615 800"/>
<rect class="entity" x="1285" y="255" width="235" height="95" rx="14"/><text class="label" x="1310" y="295">Public Display</text><text class="small" x="1325" y="323">Views live queue</text><path class="line" d="M1140 440H1285V303"/>
<rect class="service" x="1285" y="520" width="235" height="95" rx="14"/><text class="label" x="1320" y="560">SMS / Email</text><text class="small" x="1332" y="588">Delivers alerts</text><path class="line" d="M795 550H1285"/>
'''

context_body_v2 = '''
<rect class="process" x="530" y="245" width="540" height="330" rx="24"/><text class="label" x="690" y="360">SMART QMS</text><text class="label" x="615" y="398">Queue Management System</text><text class="small" x="660" y="445">Web application and operational data</text><text class="small" x="660" y="473">for tickets, queues, services and reports</text>
<rect class="entity" x="75" y="175" width="270" height="108" rx="14"/><text class="label" x="155" y="222">Client</text><text class="small" x="130" y="252">Web / mobile user</text>
<rect class="entity" x="75" y="635" width="270" height="108" rx="14"/><text class="label" x="115" y="682">Administrator</text><text class="small" x="133" y="712">System manager</text>
<rect class="entity" x="1255" y="175" width="270" height="108" rx="14"/><text class="label" x="1315" y="222">Service Staff</text><text class="small" x="1325" y="252">Window operator</text>
<rect class="entity" x="1255" y="400" width="270" height="108" rx="14"/><text class="label" x="1300" y="447">Public Display</text><text class="small" x="1320" y="477">Live queue board</text>
<rect class="service" x="1255" y="635" width="270" height="108" rx="14"/><text class="label" x="1320" y="682">External Services</text><text class="small" x="1305" y="712">ML prediction and notifications</text>
<path class="flow" d="M345 215H530"/><text class="flowlabel" x="358" y="192">registration, booking, queue request</text><path class="flow" d="M530 275H345"/><text class="flowlabel" x="362" y="303">ticket, QR, status and alerts</text>
<path class="flow" d="M345 675H425V615H530"/><text class="flowlabel" x="350" y="650">configuration and report request</text><path class="flow" d="M530 555H425V710H345"/><text class="flowlabel" x="355" y="735">dashboards and reports</text>
<path class="flow" d="M1070 215H1255"/><text class="flowlabel" x="1090" y="192">check-in and queue actions</text><path class="flow" d="M1255 275H1070"/><text class="flowlabel" x="1085" y="303">next ticket and window state</text>
<path class="flow" d="M1070 455H1255"/><text class="flowlabel" x="1090" y="432">live queue and window status</text>
<path class="flow" d="M1070 545H1255V680"/><text class="flowlabel" x="1080" y="610">features, alerts and delivery status</text><path class="flow" d="M1255 715H1070"/><text class="flowlabel" x="1110" y="742">prediction result</text>
'''

erd_body_v2 = '''
<rect class="erd" x="65" y="155" width="300" height="180" rx="12"/><rect class="erdhead" x="65" y="155" width="300" height="55" rx="12"/><text class="label" x="145" y="192">USER</text><text class="small" x="90" y="240">PK  user_id</text><text class="small" x="90" y="270">role, name, contact</text><text class="small" x="90" y="300">client_type, active status</text>
<rect class="erd" x="65" y="455" width="300" height="160" rx="12"/><rect class="erdhead" x="65" y="455" width="300" height="55" rx="12"/><text class="label" x="135" y="492">STAFF</text><text class="small" x="90" y="540">PK  staff_id</text><text class="small" x="90" y="570">FK  user_id</text><text class="small" x="90" y="600">department, shift</text>
<rect class="erd" x="490" y="155" width="330" height="180" rx="12"/><rect class="erdhead" x="490" y="155" width="330" height="55" rx="12"/><text class="label" x="535" y="192">HEALTH_SERVICE</text><text class="small" x="515" y="240">PK  service_id</text><text class="small" x="515" y="270">name, queue_mode</text><text class="small" x="515" y="300">duration, active status</text>
<rect class="erd" x="1040" y="155" width="330" height="180" rx="12"/><rect class="erdhead" x="1040" y="155" width="330" height="55" rx="12"/><text class="label" x="1085" y="192">SERVICE_WINDOW</text><text class="small" x="1065" y="240">PK  window_id</text><text class="small" x="1065" y="270">FK  staff_id, service_id</text><text class="small" x="1065" y="300">name, status, active</text>
<rect class="erd" x="1040" y="455" width="330" height="135" rx="12"/><rect class="erdhead" x="1040" y="455" width="330" height="55" rx="12"/><text class="label" x="1080" y="492">COUNTER_SERVICE</text><text class="small" x="1065" y="540">PK/FK  counter_id</text><text class="small" x="1065" y="570">PK/FK  service_id</text>
<rect class="erd" x="490" y="430" width="360" height="230" rx="12"/><rect class="erdhead" x="490" y="430" width="360" height="55" rx="12"/><text class="label" x="550" y="467">QUEUE_TICKET</text><text class="small" x="515" y="518">PK  ticket_id</text><text class="small" x="515" y="548">FK  user_id, service_id, window_id</text><text class="small" x="515" y="578">reference, QR, ticket number</text><text class="small" x="515" y="608">status and lifecycle timestamps</text><text class="small" x="515" y="638">check-in method and mode</text>
<rect class="erd" x="85" y="790" width="335" height="160" rx="12"/><rect class="erdhead" x="85" y="790" width="335" height="55" rx="12"/><text class="label" x="125" y="827">WAIT_TIME_LOG</text><text class="small" x="110" y="875">PK  log_id | FK  ticket_id</text><text class="small" x="110" y="905">features, prediction, actual wait</text><text class="small" x="110" y="935">confidence and model version</text>
<rect class="erd" x="610" y="790" width="335" height="160" rx="12"/><rect class="erdhead" x="610" y="790" width="335" height="55" rx="12"/><text class="label" x="668" y="827">NOTIFICATION</text><text class="small" x="635" y="875">PK  notif_id | FK  ticket_id</text><text class="small" x="635" y="905">FK  user_id, channel, status</text><text class="small" x="635" y="935">message and sent time</text>
<rect class="erd" x="1135" y="790" width="335" height="160" rx="12"/><rect class="erdhead" x="1135" y="790" width="335" height="55" rx="12"/><text class="label" x="1222" y="827">FEEDBACK</text><text class="small" x="1160" y="875">PK  feedback_id | FK  ticket_id</text><text class="small" x="1160" y="905">FK  user_id, window_id, service_id</text><text class="small" x="1160" y="935">rating, comment, submitted time</text>
<path class="line" d="M215 335V455"/><text class="relation" x="225" y="390">1 to 0..1</text><path class="line" d="M365 250L490 510"/><text class="relation" x="375" y="385">1 to many</text>
<path class="line" d="M655 335V430"/><text class="relation" x="670" y="390">1 to many</text><path class="line" d="M820 245H1040"/><text class="relation" x="855" y="235">1 to many</text><path class="line" d="M1205 335V455"/><text class="relation" x="1218" y="400">1 to many</text>
<path class="line" d="M580 660L280 790"/><text class="relation" x="370" y="740">1 to 1</text><path class="line" d="M670 660V790"/><text class="relation" x="680" y="730">1 to many</text><path class="line" d="M780 660L1260 790"/><text class="relation" x="1030" y="740">1 to 0..1</text>
'''

usecase_body_v2 = '''
<rect class="boundary" x="350" y="125" width="890" height="730" rx="22"/><text class="boundarylabel" x="390" y="163">SMART QMS</text>
<rect class="entity" x="60" y="290" width="220" height="100" rx="14"/><text class="label" x="117" y="335">Client</text><text class="small" x="95" y="364">Web / mobile user</text>
<rect class="entity" x="1320" y="345" width="220" height="100" rx="14"/><text class="label" x="1351" y="390">Service Staff</text><text class="small" x="1350" y="419">Window operator</text>
<rect class="entity" x="60" y="700" width="220" height="100" rx="14"/><text class="label" x="90" y="745">Administrator</text><text class="small" x="100" y="774">System manager</text>
<ellipse class="uc" cx="610" cy="205" rx="185" ry="44"/><text class="label" x="502" y="214">Register / Sign In</text>
<ellipse class="uc" cx="610" cy="315" rx="185" ry="44"/><text class="label" x="490" y="324">Join or Schedule Queue</text>
<ellipse class="uc" cx="610" cy="425" rx="185" ry="44"/><text class="label" x="475" y="434">Track Ticket &amp; Wait Time</text>
<ellipse class="uc" cx="610" cy="535" rx="185" ry="44"/><text class="label" x="476" y="544">Receive Alerts / Feedback</text>
<ellipse class="uc" cx="980" cy="270" rx="185" ry="44"/><text class="label" x="870" y="279">Check In / Call Next</text>
<ellipse class="uc" cx="980" cy="380" rx="185" ry="44"/><text class="label" x="852" y="389">Start / Complete Service</text>
<ellipse class="uc" cx="980" cy="490" rx="185" ry="44"/><text class="label" x="865" y="499">Skip, Void &amp; Recall</text>
<ellipse class="uc" cx="980" cy="600" rx="185" ry="44"/><text class="label" x="855" y="609">Manage Window Status</text>
<ellipse class="uc" cx="610" cy="690" rx="185" ry="44"/><text class="label" x="485" y="699">Manage Users &amp; Staff</text>
<ellipse class="uc" cx="970" cy="735" rx="185" ry="52"/><text class="label" x="877" y="727">Manage Services</text><text class="label" x="916" y="758">&amp; Windows</text>
<ellipse class="uc" cx="610" cy="800" rx="185" ry="44"/><text class="label" x="495" y="809">View Analytics &amp; Reports</text>
<path class="line" d="M280 340H425V205"/><path class="line" d="M280 340H425V315"/><path class="line" d="M280 340H425V425"/><path class="line" d="M280 340H425V535"/>
<path class="line" d="M1320 395H1165V270"/><path class="line" d="M1320 395H1165V380"/><path class="line" d="M1320 395H1165V490"/><path class="line" d="M1320 395H1165V600"/>
<path class="line" d="M280 750H425V690"/><path class="line" d="M280 750H785V735"/><path class="line" d="M280 750H425V800"/>
'''

files = {
    "smartqms-data-flow-diagram.svg": svg(1600, 900, "Smart QMS - Level 1 Data Flow Diagram", "Main queue, prediction, notification and reporting flows", dfd_body, "Level 1 Smart QMS data flow diagram with spacious labels and no source watermark."),
    "smartqms-context-diagram.svg": svg(1600, 900, "Smart QMS - Context Diagram", "External users and services that exchange data with the system", context_body_v2, "Context diagram for Smart Queue Management System."),
    "smartqms-erd.svg": svg(1600, 1040, "Smart QMS - Entity Relationship Diagram", "Core operational entities and their primary relationships", erd_body_v2, "Entity relationship diagram for the current Smart Queue Management System data model."),
    "smartqms-use-case-diagram.svg": svg(1600, 900, "Smart QMS - Use Case Diagram", "Functions available to clients, service staff and administrators", usecase_body_v2, "Use case diagram for Smart Queue Management System."),
}

for name, content in files.items():
    (OUT / name).write_text(content, encoding="utf-8")
    print(OUT / name)
