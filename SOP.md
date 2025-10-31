# Standard Operating Procedure (SOP) - Employee Transportation System

## 1. System Overview

The Employee Transportation System (ETS) is a comprehensive web-based application designed to manage employee transportation services. It includes features for trip management, driver management, vehicle tracking, billing, and reporting.

## 2. User Roles and Responsibilities

### 2.1 System Administrator
- **Access Level**: Full system access
- **Responsibilities**:
  - User management (create/edit/delete users)
  - System configuration and maintenance
  - Billing and financial reporting
  - Client management
  - Branch management
  - System backups and security

### 2.2 Supervisor
- **Access Level**: Branch-level access
- **Responsibilities**:
  - Trip creation and management within their branch
  - Driver assignment and monitoring
  - Vehicle allocation
  - Attendance management
  - Local reporting

### 2.3 Driver
- **Access Level**: Limited to assigned trips
- **Responsibilities**:
  - Trip execution (start/end trips)
  - Location tracking
  - OTP/QR code management
  - Basic trip status updates

## 3. Daily Operations

### 3.1 Morning Operations (Pre-Trip)

#### Trip Creation Process:
1. **Login** to the system using assigned credentials
2. **Navigate** to Trips → Create New Trip
3. **Select Vehicle** from available vehicles (shows registration number and driver details)
4. **Enter Trip Details**:
   - Trip type (Pickup/Drop/Pickup & Drop)
   - Passenger count
   - Client assignment (optional)
   - Trip date and scheduled times
5. **Location Setup**:
   - Enter pickup location (use address search or manual entry)
   - Enter drop location (use address search or manual entry)
   - System calculates route and estimated duration
6. **Passenger Information**:
   - First passenger details (name, phone, address)
   - Last passenger details (name, phone, address)
7. **Review and Submit**:
   - Verify all information
   - Submit trip (generates OTP and QR codes)
   - System sends WhatsApp notifications to passengers

#### Driver Assignment:
- Vehicles are pre-assigned to drivers in the system
- When selecting a vehicle, the assigned driver is automatically populated
- System validates driver availability and status

### 3.2 Trip Execution

#### Driver Check-in Process:
1. **Driver Login** to mobile/web interface
2. **View Assigned Trips** for the day
3. **Start Trip**:
   - Verify identity using OTP/QR code
   - Confirm passenger pickup
   - Update trip status to "In Progress"
   - System starts GPS tracking

#### During Trip:
- **Real-time Tracking**: GPS coordinates logged every few minutes
- **Status Updates**: Automatic status changes based on location
- **Communication**: WhatsApp notifications for status updates

#### Trip Completion:
1. **End Trip Process**:
   - Driver arrives at drop location
   - Passenger verifies completion using OTP/QR code
   - Driver enters final location coordinates
   - System calculates actual distance and duration
2. **Billing Calculation**:
   - Base fare calculation based on distance and vehicle type
   - Fuel costs and surcharges (if applicable)
   - Driver advance deduction
   - Final amount computation
3. **Payment Processing**:
   - Generate invoice
   - Mark payment status
   - Send confirmation to stakeholders

### 3.3 Evening Operations (Post-Trip)

#### Supervisor Review:
1. **Review Completed Trips**:
   - Verify trip completion
   - Check GPS tracking data
   - Validate billing information
   - Approve/reject trip records

#### Billing and Reporting:
1. **Generate Reports**:
   - Daily trip summary
   - Revenue reports
   - Driver performance metrics
   - Vehicle utilization reports

#### Driver Management:
1. **Attendance Marking**:
   - Automatic check-out based on trip completion
   - Manual attendance correction if needed
2. **Driver Payments**:
   - Process driver salary calculations
   - Account for advances and deductions

## 4. System Maintenance

### 4.1 Daily Maintenance
- **Database Backup**: Automated daily backups
- **Log Review**: Check system logs for errors
- **GPS Data Cleanup**: Archive old tracking data
- **WhatsApp Log Cleanup**: Remove old message logs

### 4.2 Weekly Maintenance
- **Performance Monitoring**: Check system response times
- **Storage Management**: Monitor disk space usage
- **User Activity Review**: Audit user access patterns
- **Vehicle Maintenance Updates**: Update vehicle service records

### 4.3 Monthly Maintenance
- **Financial Reconciliation**: Match system records with bank statements
- **Report Generation**: Monthly business reports
- **User Access Review**: Verify user permissions
- **System Updates**: Apply security patches and updates

## 5. Emergency Procedures

### 5.1 System Downtime
1. **Immediate Response**:
   - Notify IT support team
   - Switch to manual operations if critical
   - Communicate with stakeholders

2. **Recovery Process**:
   - Restore from latest backup
   - Verify data integrity
   - Test all system functions

### 5.2 Trip Disruptions
1. **Driver Issues**:
   - Reassign driver from available pool
   - Update passenger via WhatsApp
   - Log incident for review

2. **Vehicle Breakdown**:
   - Arrange replacement vehicle
   - Update trip records
   - Process appropriate billing adjustments

### 5.3 Security Incidents
1. **Unauthorized Access**:
   - Immediately disable affected accounts
   - Change system passwords
   - Audit access logs

2. **Data Breach**:
   - Isolate affected systems
   - Notify relevant authorities
   - Implement corrective measures

## 6. Quality Assurance

### 6.1 Trip Quality Metrics
- **On-time Performance**: Percentage of trips completed on schedule
- **GPS Accuracy**: Location tracking reliability
- **Communication Success**: WhatsApp message delivery rates
- **Customer Satisfaction**: Passenger feedback scores

### 6.2 System Performance Metrics
- **Uptime**: System availability percentage
- **Response Time**: Average page load times
- **Error Rate**: System error frequency
- **Data Accuracy**: Billing and reporting accuracy

## 7. Training and Documentation

### 7.1 User Training
- **New User Onboarding**: Comprehensive training program
- **Role-specific Training**: Customized training for each user type
- **System Updates**: Training for new features and changes

### 7.2 Documentation
- **User Manuals**: Detailed guides for each module
- **Process Documentation**: Step-by-step procedures
- **Troubleshooting Guides**: Common issues and solutions
- **API Documentation**: For integration partners

## 8. Compliance and Security

### 8.1 Data Protection
- **GDPR Compliance**: Personal data handling procedures
- **Data Encryption**: Secure data storage and transmission
- **Access Controls**: Role-based permissions

### 8.2 Safety Compliance
- **Driver Safety**: Vehicle maintenance and driver training
- **Passenger Safety**: Emergency procedures and protocols
- **Insurance Requirements**: Coverage verification

### 8.3 Financial Compliance
- **Tax Compliance**: Proper invoicing and tax calculations
- **Audit Trail**: Complete transaction logging
- **Financial Reporting**: Accurate revenue and expense tracking

## 9. Contact Information

### 9.1 Support Contacts
- **IT Support**: it@msinfosystems.co.in
- **Operations Support**: operations@msinfosystems.co.in
- **Billing Support**: billing@msinfosystems.co.in

### 9.2 Emergency Contacts
- **System Emergency**: +91-XXXXXXXXXX (24/7)
- **Operations Emergency**: +91-XXXXXXXXXX (Business Hours)

## 10. Revision History

| Version | Date | Description | Author |
|---------|------|-------------|--------|
| 1.0 | 2025-10-29 | Initial SOP creation | System Admin |
| 1.1 | [Future Date] | [Updates] | [Author] |

---

**Document Owner**: MS Infosystems Operations Team
**Review Frequency**: Quarterly
**Last Reviewed**: 2025-10-29
**Next Review**: 2026-01-29