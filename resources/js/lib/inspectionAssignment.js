export function eligibleAssignmentUsers(users, responsibility) {
    const role = { approver: 'reviewer', releaser: 'releaser' }[responsibility];
    return role ? users.filter(user => user.account_type === 'member' && user.operational_role === role) : users;
}
