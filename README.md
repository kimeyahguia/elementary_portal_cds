ABSTRACT
The rapid advancement of digital technologies has significantly influenced the
management of educational institutions, driving the need for efficient, centralized, and userfriendly information systems. Despite these developments, many schools, particularly small
private institutions, continue to rely on manual processes for student records, enrollment,
attendance, grading, and communication, resulting in inefficiencies, delays, and potential errors.
This study presents the design and development of a Web-Based School Information Portal with
Student Performance Prediction Using Random Forest Algorithm for Creative Dreams School
(CDS), a private elementary institution in Nasugbu, Batangas.
The proposed system centralizes academic and administrative operations by providing
dedicated dashboards for administrators, teachers, students, and parents. Key functionalities
include enrollment scheduling, digital document requests, grade and attendance management,
announcements, and financial tracking, while maintaining strict data privacy standards.
Additionally, the system integrates predictive analytics using the Random Forest Algorithm to
identify students at risk of underperforming, enabling early interventions to support academic
growth.
By automating repetitive tasks, improving communication, and providing real-time access
to essential information, the CDS Portal enhances operational efficiency, reduces human error,
and strengthens engagement among all stakeholders. This implementation demonstrates the
value of adopting modern, data-driven platforms in educational settings, contributing to
improved administrative coordination, academic monitoring, and overall student outcomes.



SYSTEM REQUIREMENTS
4.1 Functional Requirements
• Secure Login and User Roles. The system should allow admins, teachers,students,
and parents to log in using their assigned accounts. Each user type must see only the
features intended for their role.
• Enrollment Schedule Management. The system should display updated enrollment
schedules and allow administrators to make changes when needed. Students and parents
should be able to check enrollment dates easily and receive reminders.
• Payment and Finance Tracking. The portal should record payments, track outstanding
balances, and store digital receipts. Administrators must be able to generate financial
summaries and reports for transparency.
• Student Records Management. The system should securely store student
information, documents, and academic data. Records must be easy to update and safe
from unauthorized access.
• Grades Submission and Viewing. Teachers should be able to input and update
grades, while students and parents should be able to view them in real time.
• Attendance Monitoring. Teachers should be able to log daily attendance, and
students/parents should be able to check attendance history instantly.
SAD Final Documentation Report: Portalix 20
• Announcements and Communication. The system should provide a simple
communication space where admins and teachers can post announcements. Users should
receive notifications for important updates.
• Basic Analytics Features. The system should provide helpful insights, such as student
performance trends and early warnings for at-risk students, based on available data.
• Responsive and User-Friendly Interface. The portal must be accessible on both
desktop and mobile devices, ensuring a smooth experience even for users who are not
very tech-savvy.
• Digital Document Storage. The system should keep important school files digitally for
quick retrieval and data backup.
4.2 User Requirements
• Basic Familiarity With Technology. Users should be comfortable with basic digital
tasks such as clicking buttons, filling out forms, and navigating menus. Teachers and staff
should know how to encode or upload data.
• Access to a Device and Internet. Users need a mobile phone, tablet, or computer
with internet access. Since connectivity may vary, even intermittent but working internet
is acceptable.
• Registered Account and Updated Information. Users must have an official schoolissued account. Parents and students also need to keep their contact information updated
for announcements and reminders.
• Responsibility in Using the System. Teachers and admins must handle data
responsibly and follow school guidelines for privacy and security. Students and parents
should avoid sharing their login details with others.
• Willingness to Adapt to Digital Processes. All users should be open to using the
online portal for tasks such as viewing grades, checking announcements, or completing
enrollment and payments.
• Compliance With School Policies. Users should understand and follow existing school
policies, including the fact that some verifications (such as final grade approval) will still
be done manually to ensure accuracy.
4.3 Input Requirements
• User login credentials (username and password) for authentication of administrators,
teachers, parents, and students.
• Student information including full name, grade level, section, birthdate, and contact
details.
• Parent/guardian details such as name, relationship, phone number, and email address.
SAD Final Documentation Report: Portalix 21
• Teacher and staff information including position, department, and assigned subjects or
grade levels.
• Enrollment details such as student grade level, academic year, and payment confirmation.
• Attendance records are inputted daily by teachers.
• Grades and performance data entered by teachers per subject and grading period.
• Announcements and updates created by administrators or teachers for the school
community.
• Feedback or inquiries from parents or students submitted through the portal.
4.4 Output Requirements
• Dashboard summaries display key information such as student count, pending payments,
and active announcements.
• Enrollment reports detailing the number of enrolled students per grade level or academic
year.
• Grade reports viewable by students and parents, summarizing academic performance per
subject and term.
• Attendance reports showing student attendance records and percentage of
absences/presences.
• Financial reports summarizing payments received, outstanding balances, and transaction
history.
• Announcement notifications are delivered to students, parents, and teachers in real time.
• User activity logs for administrators to monitor system usage and actions taken.
• Analytical summaries (basic analytics) highlight trends such as academic performance or
attendance issues.
• Backup and exportable files for administrative reports, ensuring data security and
accessibility.
4.5 Control Requirements
• User Authentication and Authorization – Only registered users with valid credentials can
access the system; access levels differ by role (admin, teacher, parent, student).
• Data Validation – All user inputs must be validated (e.g., correct data formats, required
fields completed) before submission.
• Access Control – Sensitive modules (e.g., grades, payments) are restricted to authorized
personnel only.
• Session Management – Automatic logout after a defined period of inactivity to prevent
unauthorized use.
• Data Backup and Recovery – Regular backups of system data should be performed to
prevent data loss.
• Error Handling and Alerts – The system should display informative error messages and
log system errors for troubleshooting.
• Encryption and Privacy Protection – Sensitive data (e.g., passwords, financial records)
must be encrypted during storage and transmission.
SAD Final Documentation Report: Portalix 22
• Version Control and Updates – Any changes or updates to the system must be properly
logged and tested before deployment.
