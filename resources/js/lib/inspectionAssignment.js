export function eligibleAssignmentUsers(users, responsibility) {
    const role = { approver: 'reviewer', releaser: 'releaser' }[responsibility];
    return role ? users.filter(user => ['member', 'company_admin'].includes(user.account_type) && user.operational_role === role) : users;
}
